<?php

use App\Jobs\MeldGebruiksrechten;
use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Company;
use App\Models\ContentNote;
use App\Models\Location;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Status;
use App\Models\Task;
use App\Models\UsageRight;
use App\Models\User;
use App\Models\VideoIdea;
use App\Notifications\PlatformMelding;
use App\Services\Gebruiksrechten;
use App\Services\Koppelingen\InstagramPlatform;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

const CONTENT_APP = 'http://app.mebranding.test';

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
    $this->bloesem = Company::where('name', 'Lunchroom Bloesem')->first();
    $this->samenwerking = fn (Company $bedrijf, array $extra = []) => Collaboration::create($extra + [
        'number' => 'T'.fake()->unique()->numerify('#####'), 'company_id' => $bedrijf->id,
        'location_id' => $bedrijf->locations()->first()->id, 'title' => 'Kerstlunch', 'briefing' => 'Test',
        'status' => Status::Live->value, 'points' => 3,
    ]);
});

function anderBedrijf(): array
{
    $eigenaar = User::factory()->create();
    $eigenaar->assignRole('Ondernemer Eigenaar');
    $bedrijf = Company::create(['name' => 'Bakkerij Anders']);
    $bedrijf->users()->attach($eigenaar->id, ['role' => 'eigenaar']);
    Location::create(['company_id' => $bedrijf->id, 'name' => 'Dokkum', 'city' => 'Dokkum']);

    return [$bedrijf, $eigenaar];
}

test('toestemming wordt gelogd met datum en versie, daarna handmatige koppeling zonder sleutels', function () {
    $marit = ($this->wie)('marit');
    expect(app(InstagramPlatform::class)->isIngesteld())->toBeFalse();

    // Zonder toestemming geen koppeling
    $this->actingAs($marit)->post(CONTENT_APP.'/creator/content/koppelingen/instagram/handmatig', ['handle' => '@maritmaakt', 'professioneel' => '1'])
        ->assertSessionHasErrors('akkoord');

    $this->actingAs($marit)->post(CONTENT_APP.'/creator/content/koppelingen/instagram/toestemming', ['akkoord' => '1'])
        ->assertSessionHas('melding', fn ($m) => str_contains($m, 'binnenkort beschikbaar'));

    $account = SocialAccount::sole();
    expect($account->consent_version)->toBe(config('content.consent_version'))
        ->and($account->consent_at)->not->toBeNull()
        ->and($account->owner_id)->toBe($marit->creator->id);

    $log = ActivityLog::where('event', 'toestemming_gegeven')->sole();
    expect($log->data['versie'])->toBe('0.1-concept')->and($log->user_id)->toBe($marit->id);

    $this->actingAs($marit)->post(CONTENT_APP.'/creator/content/koppelingen/instagram/handmatig', ['handle' => 'maritmaakt', 'professioneel' => '1'])
        ->assertSessionHasNoErrors();
    expect($account->fresh())->status->toBe('handmatig')->handle->toBe('@maritmaakt')->professional_confirmed->toBeTrue();

    // Nieuwe versie van de tekst: toestemming opnieuw nodig
    config(['content.consent_version' => '0.2']);
    expect($account->fresh()->hasCurrentConsent())->toBeFalse();
});

test('met sleutels gaat koppelen naar het inlogscherm van Instagram', function () {
    config(['services.instagram.client_id' => 'abc', 'services.instagram.client_secret' => 'geheim']);
    $this->actingAs(($this->wie)('sanne'))
        ->post(CONTENT_APP.'/ondernemer/content/koppelingen/instagram/toestemming', ['akkoord' => '1'], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location');
    expect(SocialAccount::sole()->owner_type)->toBe((new Company)->getMorphClass());
});

test('tokens staan versleuteld in de database en gaan niet mee naar het scherm', function () {
    $account = SocialAccount::create([
        'owner_type' => (new Company)->getMorphClass(), 'owner_id' => $this->bloesem->id, 'platform' => 'instagram',
        'status' => 'gekoppeld', 'access_token' => 'IGQVJ-geheim-token', 'refresh_token' => 'ververs-geheim', 'expires_at' => now()->addDays(60),
    ]);

    $ruw = DB::table('social_accounts')->where('id', $account->id)->first();
    expect($ruw->access_token)->not->toContain('geheim')->and($ruw->refresh_token)->not->toContain('geheim')
        ->and($account->fresh()->access_token)->toBe('IGQVJ-geheim-token')
        ->and($account->toArray())->not->toHaveKey('access_token');

    $this->actingAs(($this->wie)('ruben'))->get(CONTENT_APP.'/team/content/koppelingen')->assertOk()
        ->assertDontSee('geheim-token');
    $this->actingAs(($this->wie)('sanne'))->get(CONTENT_APP.'/ondernemer/content/koppelingen')->assertOk()
        ->assertDontSee('geheim-token');
});

test('monitor alleen met koppelingen.beheren; waarschuwing bij verloopt binnen 7 dagen', function () {
    SocialAccount::create(['owner_type' => (new Company)->getMorphClass(), 'owner_id' => $this->bloesem->id, 'platform' => 'instagram', 'status' => 'gekoppeld', 'expires_at' => now()->addDays(5)]);

    $this->actingAs(($this->wie)('eva'))->get(CONTENT_APP.'/team/content/koppelingen')->assertForbidden();
    $this->actingAs(($this->wie)('ruben'))->get(CONTENT_APP.'/team/content/koppelingen')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('team/content/koppelingen')->where('accounts.0.waarschuwing', 'verloopt'));
});

test('controle post verwijderd: gevonden of niet gevonden met logregel, zonder strafpunten', function () {
    $c = ($this->samenwerking)($this->bloesem, ['creator_id' => ($this->wie)('marit')->creator->id]);
    $p = Publication::create(['collaboration_id' => $c->id, 'platform' => 'instagram', 'url' => 'https://instagram.com/p/x', 'posted_at' => now()->subDay(), 'last_found_at' => now()->subDay()]);
    $ruben = ($this->wie)('ruben');

    $this->travel(3)->hours();
    $this->actingAs($ruben)->post(CONTENT_APP."/team/content/publicaties/{$p->id}/controle", ['found' => '1'])->assertSessionHasNoErrors();
    expect($p->fresh()->last_found_at->isToday())->toBeTrue();

    Notification::fake();
    $voor = $p->fresh()->last_found_at;
    $this->actingAs($ruben)->post(CONTENT_APP."/team/content/publicaties/{$p->id}/controle", ['found' => '0', 'note' => 'Gearchiveerd', 'melden' => '1'])->assertSessionHasNoErrors();
    expect($p->fresh()->last_found_at->equalTo($voor))->toBeTrue()
        ->and(ActivityLog::where('subject_type', $p->getMorphClass())->pluck('event')->all())->toBe(['post_gevonden', 'post_niet_gevonden']);
    Notification::assertSentTo(($this->wie)('marit'), PlatformMelding::class);

    $this->actingAs(($this->wie)('eva'))->post(CONTENT_APP."/team/content/publicaties/{$p->id}/controle", ['found' => '1'])->assertForbidden();
});

test('ondernemer ziet alleen de eigen kalender en de eigen gebruiksrechten', function () {
    [$ander, $andereEigenaar] = anderBedrijf();
    $eigen = ($this->samenwerking)($this->bloesem, ['title' => 'Eigen kerstlunch', 'live_at' => now()]);
    $vreemd = ($this->samenwerking)($ander, ['title' => 'Vreemde taart', 'live_at' => now()]);
    app(Gebruiksrechten::class)->standaard($eigen);
    app(Gebruiksrechten::class)->standaard($vreemd);
    ContentNote::create(['company_id' => $ander->id, 'date' => today(), 'title' => 'Geheim plan']);
    $vreemdeNotitie = ContentNote::first();
    $sanne = ($this->wie)('sanne');

    $this->actingAs($sanne)->get(CONTENT_APP.'/ondernemer/content/kalender')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('ondernemer/content/kalender')
            ->where('kalender.items', fn ($items) => collect($items)->pluck('titel')->all() === ['Eigen kerstlunch']));

    $this->actingAs($sanne)->get(CONTENT_APP.'/ondernemer/content/rechten')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->has('rights', 2)
            ->where('rights', fn ($r) => collect($r)->every(fn ($x) => $x['collaboration']['title'] === 'Eigen kerstlunch')));

    $this->actingAs($sanne)->delete(CONTENT_APP."/ondernemer/content/notities/{$vreemdeNotitie->id}")->assertForbidden();
    $vreemdRecht = UsageRight::where('collaboration_id', $vreemd->id)->first();
    $this->actingAs($sanne)->post(CONTENT_APP."/ondernemer/content/rechten/{$vreemdRecht->id}/verlengen")->assertForbidden();

    // Notitie toevoegen op de eigen kalender
    $this->actingAs($sanne)->post(CONTENT_APP.'/ondernemer/content/notities', ['date' => today()->toDateString(), 'title' => 'Kerstmenu filmen'])->assertSessionHasNoErrors();
    expect(ContentNote::where('company_id', $this->bloesem->id)->count())->toBe(1);
});

test('standaardrechten bij Live (VOORSTEL 12 en 3 maanden) en melding 30 en 7 dagen vooraf via de geplande job', function () {
    $c = ($this->samenwerking)($this->bloesem, ['live_at' => now()]);
    app(Gebruiksrechten::class)->standaard($c);
    $advertentie = UsageRight::where('kind', 'advertentie')->sole();
    expect($advertentie->ends_on->toDateString())->toBe(today()->addMonths(3)->toDateString())
        ->and(UsageRight::where('kind', 'organisch')->sole()->ends_on->toDateString())->toBe(today()->addMonths(12)->toDateString())
        ->and($advertentie->is_proposal)->toBeTrue();

    Notification::fake();
    $sanne = ($this->wie)('sanne');
    $meldingen = fn () => Notification::sent($sanne, PlatformMelding::class)->count();

    (new MeldGebruiksrechten)->handle(app(Gebruiksrechten::class));
    expect($meldingen())->toBe(0);

    $this->travelTo($advertentie->ends_on->copy()->subDays(30)->setTime(9, 0));
    (new MeldGebruiksrechten)->handle(app(Gebruiksrechten::class));
    (new MeldGebruiksrechten)->handle(app(Gebruiksrechten::class)); // geen dubbele melding
    expect($meldingen())->toBe(1)->and($advertentie->fresh()->notified)->toBe([30]);

    $this->travelTo($advertentie->ends_on->copy()->subDays(7)->setTime(9, 0));
    (new MeldGebruiksrechten)->handle(app(Gebruiksrechten::class));
    expect($meldingen())->toBe(2)->and($advertentie->fresh()->notified)->toBe([30, 7]);

    $this->travelTo($advertentie->ends_on->copy()->addDay());
    (new MeldGebruiksrechten)->handle(app(Gebruiksrechten::class));
    expect($advertentie->fresh()->status)->toBe('verlopen')->and($meldingen())->toBe(2);
});

test('video-ideeën: ondernemer en team voegen toe, team verwijst bij een aanvraag', function () {
    $sanne = ($this->wie)('sanne');
    $eva = ($this->wie)('eva');
    $c = ($this->samenwerking)($this->bloesem, ['status' => Status::Nieuw->value]);

    $this->actingAs($sanne)->post(CONTENT_APP.'/ondernemer/content/ideeen', ['title' => 'Van pan tot kom'])->assertSessionHasNoErrors();
    $this->actingAs($eva)->post(CONTENT_APP."/team/content/bedrijven/{$this->bloesem->id}/ideeen", ['title' => 'Kerstmenu in 15 seconden'])->assertSessionHasNoErrors();
    $idee = VideoIdea::where('source', 'ondernemer')->sole();

    $this->actingAs($eva)->patch(CONTENT_APP."/team/content/ideeen/{$idee->id}", ['status' => 'idee', 'collaboration_id' => $c->id])->assertSessionHasNoErrors();
    expect($idee->fresh())->status->toBe('gepland')->collaboration_id->toBe($c->id);

    $this->actingAs($sanne)->get(CONTENT_APP.'/ondernemer/content/ideeen')
        ->assertInertia(fn (AssertableInertia $p) => $p->has('ideas', 2));
});

test('planning met recht planning.beheren, radar-interesse maakt een taak voor sales', function () {
    $this->actingAs(($this->wie)('eva'))->get(CONTENT_APP.'/team/content/planning')->assertForbidden();
    $planner = User::factory()->create();
    $planner->assignRole('Planning');
    ($this->samenwerking)($this->bloesem, ['status' => Status::BezoekGepland->value, 'visit_at' => now()->startOfWeek()->addDay()->setTime(12, 0)]);
    $this->actingAs($planner)->get(CONTENT_APP.'/team/content/planning')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('team/content/planning')->has('visits', 1));

    $this->actingAs(($this->wie)('sanne'))->post(CONTENT_APP.'/ondernemer/content/zichtbaarheid/interesse')->assertSessionHasNoErrors();
    $this->actingAs(($this->wie)('sanne'))->post(CONTENT_APP.'/ondernemer/content/zichtbaarheid/interesse');
    expect(Task::where('type', 'radar_interesse')->sole())->permission->toBe('sales.beheren')->company_id->toBe($this->bloesem->id);
});
