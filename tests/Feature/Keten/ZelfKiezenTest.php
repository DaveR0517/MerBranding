<?php

use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Creator;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\Screening;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * De ondernemer kiest de creator rechtstreeks, of laat MEBRANDING kiezen.
 * Voorbeeld van Dave: Cheung Kwong Franeker vraagt "Unlimited Shared Dining + 2 drankjes" aan, Marieke is net goedgekeurd.
 */
uses(RefreshDatabase::class);

const APP_ZK = 'http://app.mebranding.test';

beforeEach(function () {
    config(['mail.default' => 'log']);
    $this->seed(DemoSeeder::class);
    $this->wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();

    // Team maakt het bedrijf met eigenaar aan; zonder maildienst staat de uitnodigingslink in beeld.
    $this->actingAs(($this->wie)('eva'))->post(APP_ZK.'/team/bedrijven', [
        'name' => 'Cheung Kwong Franeker', 'branche' => 'Restaurant', 'owner_name' => 'Wei Cheung', 'owner_email' => 'eigenaar@cheungkwong.test',
        'location_name' => 'Cheung Kwong', 'location_address' => 'Voorstraat 1', 'location_city' => 'Franeker', 'plan_id' => Plan::where('name', 'Groei')->value('id'),
    ])->assertSessionHasNoErrors()->assertSessionHas('uitnodiging');
    $this->zaak = Company::where('name', 'Cheung Kwong Franeker')->sole();
    $this->eigenaar = User::where('email', 'eigenaar@cheungkwong.test')->sole();
    $this->eigenaar->markEmailAsVerified(); // zoals na wachtwoord kiezen via de uitnodigingslink

    // Marieke meldt zich aan en wordt goedgekeurd: een actieve creator van 30.
    $lead = Lead::create(['type' => Lead::TYPE_CREATOR, 'name' => 'Marieke Visser', 'email' => 'marieke@voorbeeld.test', 'city' => 'Franeker',
        'data' => ['soort' => 'ugc', 'geboortedatum' => now()->subYears(30)->toDateString(), 'instagram-naam' => 'marieke.maakt']]);
    $this->marieke = app(Screening::class)->goedkeuren($lead->fresh(), ($this->wie)('ruben'), 'bronze', 'Sterk portfolio');
    $this->marieke->user->markEmailAsVerified();

    $this->aanvraag = fn (array $extra) => $this->actingAs($this->eigenaar)->post(APP_ZK.'/ondernemer/samenwerkingen', $extra + [
        'location_id' => $this->zaak->locations()->value('id'), 'title' => 'Unlimited Shared Dining + 2 drankjes',
        'briefing' => 'Laat het onbeperkt delen van gerechten zien, met twee drankjes erbij.', 'alcohol' => true,
    ]);
});

test('de ondernemer kiest Marieke rechtstreeks: uitnodiging gaat direct, geen koppeltaak voor het team', function () {
    $this->actingAs($this->eigenaar)->get(APP_ZK.'/ondernemer/aanvragen')
        ->assertInertia(fn ($p) => $p->where('creators', fn ($lijst) => collect($lijst)->contains(fn ($m) => $m['name'] === 'Marieke Visser' && $m['vanaf_25'] && $m['points'] === 3)));

    ($this->aanvraag)(['keuze' => 'zelf', 'creator_id' => $this->marieke->id])->assertSessionHasNoErrors();

    $c = Collaboration::where('company_id', $this->zaak->id)->sole();
    $uitnodiging = $c->invitations()->sole();
    expect($c->status)->toBe(Status::CreatorZoeken)
        ->and($c->creator_level)->toBe('bronze')
        ->and($uitnodiging->creator_id)->toBe($this->marieke->id)
        ->and($uitnodiging->invited_by)->toBe($this->eigenaar->id)
        ->and(Task::open()->where('collaboration_id', $c->id)->where('type', 'koppelen')->exists())->toBeFalse()
        ->and($this->marieke->user->notifications()->where('data->key', 'c-uitnodiging')->exists())->toBeTrue();

    // De ondernemer ziet wie is uitgenodigd en kan zolang niet nog iemand kiezen.
    $this->actingAs($this->eigenaar)->get(APP_ZK."/ondernemer/samenwerkingen/{$c->id}")
        ->assertInertia(fn ($p) => $p->where('own_invitation.creator', 'Marieke Visser')->where('creators', null));

    // Marieke tekent: creator gevonden, de ondernemer krijgt bericht.
    $this->actingAs($this->marieke->user)->post(APP_ZK."/creator/uitnodigingen/{$uitnodiging->id}/accepteren", ['signed_name' => 'Marieke Visser', 'akkoord' => '1'])
        ->assertSessionHasNoErrors();
    expect($c->fresh()->status)->toBe(Status::CreatorGevonden)->and($c->fresh()->creator_id)->toBe($this->marieke->id);
});

test('zegt de gekozen creator nee, dan krijgt het team een taak en kan de ondernemer iemand anders kiezen, niet dezelfde', function () {
    ($this->aanvraag)(['keuze' => 'zelf', 'creator_id' => $this->marieke->id])->assertSessionHasNoErrors();
    $c = Collaboration::where('company_id', $this->zaak->id)->sole();

    $this->actingAs($this->marieke->user)->post(APP_ZK."/creator/uitnodigingen/{$c->invitations()->value('id')}/weigeren", ['reason' => 'Geen tijd'])
        ->assertSessionHasNoErrors();
    expect(Task::open()->where('collaboration_id', $c->id)->where('type', 'geweigerd')->exists())->toBeTrue();

    $this->actingAs($this->eigenaar)->get(APP_ZK."/ondernemer/samenwerkingen/{$c->id}")
        ->assertInertia(fn ($p) => $p->where('own_invitation', null)
            ->where('creators', fn ($lijst) => collect($lijst)->firstWhere('name', 'Marieke Visser')['blocked'] === 'Heeft deze uitnodiging al afgeslagen'));

    $this->actingAs($this->eigenaar)->post(APP_ZK."/ondernemer/samenwerkingen/{$c->id}/uitnodigen", ['creator_id' => $this->marieke->id])
        ->assertSessionHasErrors('creator');

    $ander = Creator::where('status', 'actief')->whereKeyNot($this->marieke->id)->get()->first(fn ($cr) => ($cr->age() ?? 0) >= 25);
    $this->actingAs($this->eigenaar)->post(APP_ZK."/ondernemer/samenwerkingen/{$c->id}/uitnodigen", ['creator_id' => $ander->id])
        ->assertSessionHasNoErrors();
    expect($c->invitations()->whereNull('answer')->sole()->creator_id)->toBe($ander->id)
        ->and(Task::open()->where('collaboration_id', $c->id)->where('type', 'geweigerd')->exists())->toBeFalse();
});

test('alcohol in beeld en een creator onder de 25: geweigerd, en er wordt niets aangemaakt of gereserveerd', function () {
    $jong = Creator::where('status', 'actief')->get()->first(fn ($cr) => ($cr->age() ?? 0) < 25)
        ?? tap(Creator::where('status', 'actief')->whereKeyNot($this->marieke->id)->first())->update(['birthdate' => now()->subYears(21)]);
    $voor = $this->zaak->availablePoints();

    ($this->aanvraag)(['keuze' => 'zelf', 'creator_id' => $jong->id])->assertSessionHasErrors('creator');

    expect(Collaboration::where('company_id', $this->zaak->id)->exists())->toBeFalse()
        ->and($this->zaak->fresh()->availablePoints())->toBe($voor);
});

test('via het team: MEBRANDING kiest, het team krijgt de koppeltaak en nodigt uit', function () {
    ($this->aanvraag)(['keuze' => 'team', 'creator_level' => 'bronze'])->assertSessionHasNoErrors();
    $c = Collaboration::where('company_id', $this->zaak->id)->sole();
    expect($c->status)->toBe(Status::Nieuw)
        ->and(Task::open()->where('collaboration_id', $c->id)->where('type', 'koppelen')->exists())->toBeTrue();

    // Team ziet Marieke bovenaan: gevraagd niveau en woont in Franeker.
    $this->actingAs(($this->wie)('eva'))->get(APP_ZK."/team/samenwerkingen/{$c->id}")
        ->assertInertia(fn ($p) => $p->where('matches.0.name', 'Marieke Visser')->where('matches.0.reasons', ['Gevraagd niveau', 'Woont in Franeker']));

    $this->actingAs(($this->wie)('eva'))->post(APP_ZK."/team/samenwerkingen/{$c->id}/uitnodigen", ['creator_id' => $this->marieke->id])
        ->assertSessionHasNoErrors();
    expect($c->fresh()->status)->toBe(Status::CreatorZoeken)
        ->and(Task::open()->where('collaboration_id', $c->id)->where('type', 'koppelen')->exists())->toBeFalse();

    // Een uitnodiging van het team: de ondernemer ziet de naam pas na ja.
    $this->actingAs($this->eigenaar)->get(APP_ZK."/ondernemer/samenwerkingen/{$c->id}")->assertInertia(fn ($p) => $p->where('own_invitation', null));
});
