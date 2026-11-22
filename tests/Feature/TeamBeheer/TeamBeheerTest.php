<?php

use App\Models\ActivityLog;
use App\Models\MessageTemplate;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PlatformMelding;
use App\Notifications\Testbericht;
use App\Services\Instellingen;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

const TB_APP = 'http://app.mebranding.test';

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->baas = User::factory()->create(['name' => 'Dave Baas']);
    $this->baas->assignRole('Super Admin');
    $this->eva = User::where('email', 'eva@demo.mebranding.nl')->first();
    $this->ruben = User::where('email', 'ruben@demo.mebranding.nl')->first();
});

test('instellingen overschrijven de config, ook na opnieuw opstarten, en elke wijziging staat met oud en nieuw in het logboek', function () {
    expect(config('mebranding.timers.invitation_hours'))->toBe(48);

    $this->actingAs($this->baas)->put(TB_APP.'/team/instellingen', [
        'waarden' => ['mebranding.timers.invitation_hours' => '72', 'mebranding.levels.elite.fee_cents' => '175,50', 'mebranding.timers.review_cutoff' => '15:30'],
        'voorstel' => ['mebranding.timers.invitation_hours' => false],
    ])->assertSessionHasNoErrors();

    expect(config('mebranding.timers.invitation_hours'))->toBe(72)
        ->and(config('mebranding.levels.elite.fee_cents'))->toBe(17550);

    // Opnieuw opstarten: config uit het bestand, daarna de database eroverheen (cache is geleegd bij opslaan).
    config(['mebranding.timers.invitation_hours' => 48, 'mebranding.levels.elite.fee_cents' => 16000]);
    app(Instellingen::class)->laad();
    expect(config('mebranding.timers.invitation_hours'))->toBe(72)
        ->and(config('mebranding.timers.review_cutoff'))->toBe('15:30');

    $log = ActivityLog::where('event', 'instelling_gewijzigd')->where('data->key', 'mebranding.timers.invitation_hours')->sole();
    expect($log->data['oud'])->toBe(48)->and($log->data['nieuw'])->toBe(72)->and($log->data['voorstel_nieuw'])->toBeFalse()
        ->and($log->user_id)->toBe($this->baas->id);
    expect(ActivityLog::where('event', 'instelling_gewijzigd')->count())->toBe(3);

    // Niets veranderd: geen nieuwe logregel.
    $this->actingAs($this->baas)->put(TB_APP.'/team/instellingen', ['waarden' => ['mebranding.timers.invitation_hours' => '72'], 'voorstel' => ['mebranding.timers.invitation_hours' => false]]);
    expect(ActivityLog::where('event', 'instelling_gewijzigd')->count())->toBe(3);
});

test('instellingen: ongeldige waarden en alleen met recht', function () {
    $this->actingAs($this->baas)->put(TB_APP.'/team/instellingen', ['waarden' => ['mebranding.timers.review_cutoff' => '25:00']])
        ->assertSessionHasErrors('waarden.mebranding.timers.review_cutoff');
    $this->actingAs($this->baas)->put(TB_APP.'/team/instellingen', ['waarden' => ['mebranding.app_prefix' => 'x']])->assertSessionHasErrors('waarden');
    $this->actingAs($this->eva)->get(TB_APP.'/team/instellingen')->assertForbidden();
});

test('zonder tabel settings start de app gewoon met de config', function () {
    Cache::flush();
    Schema::drop('settings');
    app(Instellingen::class)->laad();
    expect(config('mebranding.timers.measure_days'))->toBe(14);
});

test('nieuwe prijsversie gaat in op een datum na vandaag; lopende abonnementen houden hun versie', function () {
    $groei = Plan::where('name', 'Groei')->first();
    $huidig = $groei->currentVersion();

    $this->actingAs($this->baas)->post(TB_APP."/team/pakketten/{$groei->id}/versies", [
        'price' => 829, 'points_per_month' => 20, 'valid_from' => today()->toDateString(), 'is_proposal' => true,
    ])->assertSessionHasErrors('valid_from');

    $per = today()->addMonthNoOverflow()->startOfMonth();
    $this->actingAs($this->baas)->post(TB_APP."/team/pakketten/{$groei->id}/versies", [
        'price' => '829.00', 'points_per_month' => 20, 'valid_from' => $per->toDateString(), 'is_proposal' => true,
    ])->assertSessionHasNoErrors();

    expect($groei->versions()->count())->toBe(2)
        ->and($groei->currentVersion()->is($huidig))->toBeTrue();
    expect(ActivityLog::where('event', 'prijsversie_toegevoegd')->count())->toBe(1);

    $this->travelTo($per);
    expect($groei->currentVersion()->price_cents)->toBe(82900);
    expect(Subscription::first()->plan_version_id)->toBe($huidig->id);
});

test('pakket toevoegen met voorstel-markering', function () {
    $this->actingAs($this->baas)->post(TB_APP.'/team/pakketten', [
        'name' => 'Diamond', 'price' => 1750, 'points_per_month' => 60, 'valid_from' => today()->toDateString(), 'is_proposal' => true,
    ])->assertSessionHasNoErrors();

    $diamond = Plan::where('name', 'Diamond')->sole();
    expect($diamond->currentVersion()->is_proposal)->toBeTrue()->and($diamond->currentVersion()->price_cents)->toBe(175000);
    $this->actingAs($this->baas)->post(TB_APP.'/team/pakketten', ['name' => 'Diamond', 'price' => 1, 'points_per_month' => 1, 'valid_from' => today()->toDateString()])
        ->assertSessionHasErrors('name');
});

test('berichtsjabloon: verplicht kanaal kan niet uit, tekst wijzigen wordt gelogd, testmail gaat naar mij', function () {
    Notification::fake();
    $t = MessageTemplate::where('key', 't-rechten')->sole(); // e-mail verplicht, in-app bestaat niet
    $basis = ['subject' => $t->subject, 'inapp' => $t->inapp, 'email' => $t->email, 'channels' => $t->channels];

    $this->actingAs($this->baas)->put(TB_APP."/team/berichtsjablonen/{$t->id}", ['channels' => ['email' => 'uit'] + $t->channels] + $basis)
        ->assertSessionHasErrors('channels.email');
    $this->actingAs($this->baas)->put(TB_APP."/team/berichtsjablonen/{$t->id}", ['channels' => ['inapp' => 'aan'] + $t->channels] + $basis)
        ->assertSessionHasErrors('channels.inapp');
    expect($t->fresh()->channels['email'])->toBe('verplicht');

    $this->actingAs($this->baas)->put(TB_APP."/team/berichtsjablonen/{$t->id}", ['subject' => 'Je rollen zijn gewijzigd'] + $basis)->assertSessionHasNoErrors();
    expect($t->fresh()->subject)->toBe('Je rollen zijn gewijzigd');
    expect(ActivityLog::where('event', 'berichtsjabloon_gewijzigd')->sole()->data['oud']['subject'])->toBe('Je rechten zijn gewijzigd');

    // Een kanaal dat niet vastligt mag wel uit.
    $n = MessageTemplate::where('key', 'o-aangevraagd')->sole();
    $this->actingAs($this->baas)->put(TB_APP."/team/berichtsjablonen/{$n->id}", ['subject' => $n->subject, 'inapp' => $n->inapp, 'email' => $n->email, 'channels' => ['email' => 'uit'] + $n->channels])
        ->assertSessionHasNoErrors();
    expect($n->fresh()->channels['email'])->toBe('uit');

    $this->actingAs($this->baas)->post(TB_APP."/team/berichtsjablonen/{$t->id}/testmail", ['subject' => 'Concept onderwerp'] + $basis)->assertSessionHasNoErrors();
    Notification::assertSentTo($this->baas, Testbericht::class, fn (Testbericht $m) => $m->template->subject === 'Concept onderwerp' && $m->vars['wie'] === 'Mirthe Brouwer');
    expect($t->fresh()->subject)->toBe('Je rollen zijn gewijzigd'); // testmail slaat niets op
});

test('alleen een Super Admin maakt een Super Admin', function () {
    Notification::fake();
    $beheerder = User::factory()->create();
    $beheerder->givePermissionTo('team.beheren');
    $beheerder->assignRole('Planning');

    $this->actingAs($beheerder)->post(TB_APP.'/team/medewerkers', ['name' => 'Lars Kok', 'email' => 'lars@mebranding.nl', 'rollen' => ['Super Admin']])->assertForbidden();
    $this->actingAs($beheerder)->put(TB_APP."/team/medewerkers/{$this->eva->id}/rollen", ['rollen' => ['Accountmanager', 'Super Admin']])->assertForbidden();
    expect($this->eva->fresh()->hasRole('Super Admin'))->toBeFalse()->and(User::where('email', 'lars@mebranding.nl')->exists())->toBeFalse();

    $this->actingAs($this->baas)->post(TB_APP.'/team/medewerkers', ['name' => 'Lars Kok', 'email' => 'lars@mebranding.nl', 'rollen' => ['Super Admin']])->assertSessionHasNoErrors();
    expect(User::where('email', 'lars@mebranding.nl')->sole()->hasRole('Super Admin'))->toBeTrue();
});

test('medewerker uitnodigen, rollen wijzigen en deactiveren: gelogd en melding t-rechten', function () {
    Notification::fake();

    $this->actingAs($this->baas)->post(TB_APP.'/team/medewerkers', ['name' => 'Nienke Bos', 'email' => 'Nienke@mebranding.nl', 'rollen' => ['Contentcontrole', 'Accountmanager']])
        ->assertSessionHasNoErrors();
    $nienke = User::where('email', 'nienke@mebranding.nl')->sole();
    expect($nienke->hasAllRoles(['Contentcontrole', 'Accountmanager']))->toBeTrue();
    Notification::assertSentTo($nienke, PlatformMelding::class, fn ($m) => $m->template->key === 'g-uitnodiging');

    $this->actingAs($this->baas)->put(TB_APP."/team/medewerkers/{$this->ruben->id}/rollen", ['rollen' => ['Contentcontrole', 'Planning']])->assertSessionHasNoErrors();
    expect($this->ruben->fresh()->hasRole('Planning'))->toBeTrue();
    $log = ActivityLog::where('event', 'rechten_gewijzigd')->sole();
    expect($log->data)->toBe(['oud' => ['Contentcontrole'], 'nieuw' => ['Contentcontrole', 'Planning']])->and($log->subject_id)->toBe($this->ruben->id);
    Notification::assertSentTo($this->ruben, PlatformMelding::class, fn ($m) => $m->template->key === 't-rechten' && str_contains($m->vars['rollen'], 'Planning'));

    // Deactiveren: teamrollen weg, account blijft, andere rollen blijven.
    $this->ruben->assignRole('Creator');
    $this->actingAs($this->baas)->post(TB_APP."/team/medewerkers/{$this->ruben->id}/deactiveren")->assertSessionHasNoErrors();
    $ruben = $this->ruben->fresh();
    expect($ruben)->not->toBeNull()->and($ruben->getRoleNames()->all())->toBe(['Creator']);
    expect(ActivityLog::where('event', 'medewerker_gedeactiveerd')->count())->toBe(1);
    $this->actingAs($ruben)->get(TB_APP.'/team')->assertForbidden();

    // Jezelf of de laatste Super Admin: niet.
    $this->actingAs($this->baas)->post(TB_APP."/team/medewerkers/{$this->baas->id}/deactiveren")->assertSessionHasErrors('rollen');
    expect($this->baas->fresh()->hasRole('Super Admin'))->toBeTrue();

    // Zonder recht team.beheren geen toegang.
    $this->actingAs($this->eva)->get(TB_APP.'/team/medewerkers')->assertForbidden();
    $this->actingAs($this->baas)->get(TB_APP.'/team/medewerkers')->assertOk();
    $this->actingAs($this->eva)->get(TB_APP.'/team/mijn-rechten')->assertOk();
});

test('tweestaps verplicht: teamlid zonder bevestigde 2FA gaat naar de beveiligingspagina', function () {
    config(['mebranding.team_2fa_verplicht' => true]);

    $this->actingAs($this->eva)->get(TB_APP.'/team')
        ->assertRedirect(route('security.edit'))
        ->assertSessionHas('melding');

    $this->eva->forceFill(['two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($this->eva->fresh())->get(TB_APP.'/team')->assertOk();

    // Andere omgevingen hebben er geen last van.
    $sanne = User::where('email', 'sanne@demo.mebranding.nl')->first();
    $this->actingAs($sanne)->get(TB_APP.'/ondernemer')->assertOk();

    config(['mebranding.team_2fa_verplicht' => false]);
    $this->actingAs($this->ruben)->get(TB_APP.'/team')->assertOk();
});

test('foutpagina 404 in huisstijl', function () {
    $this->get('http://mebranding.test/bestaat-niet')->assertNotFound()->assertSee('bestaat niet')->assertSee('MEBRANDING');
    $this->get(TB_APP.'/bestaat-niet')->assertNotFound()->assertSee('Foutcode 404');
});
