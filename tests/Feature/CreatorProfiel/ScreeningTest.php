<?php

use App\Mail\ScreeningBericht;
use App\Models\ActivityLog;
use App\Models\Creator;
use App\Models\Lead;
use App\Models\ScreeningAanmelding;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app_ = 'http://app.mebranding.test';
    $this->seed(DemoSeeder::class);
    $this->wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
    $this->aanmelding = fn (array $extra = []) => Lead::create($extra + [
        'type' => Lead::TYPE_CREATOR, 'name' => 'Lotte Kramer', 'email' => 'lotte@voorbeeld.nl', 'phone' => '0612345678', 'city' => 'Harlingen',
        'data' => ['soort' => 'ugc', 'geboortedatum' => '2000-05-01', 'instagram-naam' => 'lotte.film', 'niches' => ['eten', 'Lifestyle'], 'straal' => '30'],
    ]);
});

test('een nieuwe aanmelding als creator geeft een screeningtaak in de werkvoorraad', function () {
    $this->post('http://mebranding.test/aanmelden-creator', [
        'voornaam' => 'Lotte', 'achternaam' => 'Kramer', 'email' => 'lotte@voorbeeld.nl', 'woonplaats' => 'Harlingen',
        'geboortedatum' => now()->subYears(25)->toDateString(), 'akkoord' => '1',
    ])->assertSessionHasNoErrors();

    $taak = Task::where('type', 'screening')->sole();
    expect($taak->permission)->toBe('creators.screenen')
        ->and($taak->closed_at)->toBeNull()
        ->and($taak->due_at->isWeekend())->toBeFalse()
        ->and(ScreeningAanmelding::sole()->task_id)->toBe($taak->id);

    // Een aanmelding als ondernemer krijgt geen screeningtaak.
    Lead::create(['type' => Lead::TYPE_ONDERNEMER, 'name' => 'X', 'email' => 'x@voorbeeld.nl']);
    expect(Task::where('type', 'screening')->count())->toBe(1);
});

test('alleen het recht creators.screenen ziet de screening', function () {
    $lead = ($this->aanmelding)();

    $this->actingAs(($this->wie)('ruben'))->get($this->app_.'/team/screening')->assertOk()->assertSee('Lotte Kramer');
    $this->actingAs(($this->wie)('ruben'))->get($this->app_."/team/screening/{$lead->id}")->assertOk();
    $this->actingAs(($this->wie)('eva'))->get($this->app_.'/team/screening')->assertForbidden();
    $this->actingAs(($this->wie)('eva'))->post($this->app_."/team/screening/{$lead->id}/afwijzen", ['reason' => 'Nee'])->assertForbidden();
    $this->actingAs(($this->wie)('marit'))->get($this->app_.'/team/screening')->assertForbidden();
    expect($lead->fresh()->status)->toBe('nieuw');
});

test('goedkeuren maakt een actieve creator, stuurt de welkomstmelding met wachtwoordlink en logt de reden', function () {
    $lead = ($this->aanmelding)();
    $ruben = ($this->wie)('ruben');

    $this->actingAs($ruben)->post($this->app_."/team/screening/{$lead->id}/goedkeuren", ['level' => 'bronze'])->assertSessionHasErrors('reason');

    $this->actingAs($ruben)->post($this->app_."/team/screening/{$lead->id}/goedkeuren", [
        'level' => 'bronze', 'reason' => 'Mooi voorbeeldwerk, accounts in orde.',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $user = User::where('email', 'lotte@voorbeeld.nl')->sole();
    $creator = Creator::where('user_id', $user->id)->sole();
    expect($user->hasRole('Creator'))->toBeTrue()
        ->and($creator->status)->toBe('actief')
        ->and($creator->level)->toBe('bronze')
        ->and($creator->kind)->toBe('ugc')
        ->and($creator->niches)->toBe(['Eten en drinken', 'Lifestyle'])
        ->and($creator->instagram)->toBe('@lotte.film')
        ->and($lead->fresh()->status)->toBe('creator')
        ->and(Task::where('type', 'screening')->sole()->closed_at)->not->toBeNull();

    $melding = $user->notifications()->sole();
    expect($melding->data['key'])->toBe('c-welkom')
        ->and($melding->data['body'])->toContain('Bronze')
        ->and($melding->data['url'])->toContain('reset-password');

    $log = ActivityLog::where('event', 'screening_goedgekeurd')->sole();
    expect($log->user_id)->toBe($ruben->id)
        ->and($log->subject_id)->toBe($lead->id)
        ->and($log->reason)->toBe('Mooi voorbeeldwerk, accounts in orde.')
        ->and($log->data['niveau'])->toBe('bronze');

    // Na de wachtwoordlink (e-mail bevestigd) komt de nieuwe creator in zijn omgeving.
    $user->markEmailAsVerified();
    $this->actingAs($user)->get($this->app_.'/creator/start')->assertOk();
});

test('aanvulling vragen mailt de aanmelder zonder account en zet de status', function () {
    Mail::fake();
    $lead = ($this->aanmelding)();

    $this->actingAs(($this->wie)('ruben'))->post($this->app_."/team/screening/{$lead->id}/aanvulling", [
        'reason' => 'Je Instagram staat op privé. Zet hem om naar een professioneel account.',
    ])->assertSessionHasNoErrors();

    expect($lead->fresh()->status)->toBe('aanvulling')
        ->and(User::where('email', 'lotte@voorbeeld.nl')->exists())->toBeFalse()
        ->and(ActivityLog::where('event', 'screening_aanvulling')->sole()->reason)->toContain('privé');
    Mail::assertQueued(ScreeningBericht::class, fn ($m) => $m->hasTo('lotte@voorbeeld.nl') && $m->sleutel === 'c-aanvulling');

    $html = (new ScreeningBericht('c-aanvulling', $lead, ['toelichting' => 'Instagram op professioneel'], 'https://mebranding.test/creators'))->render();
    expect($html)->toContain('We missen nog iets')->toContain('Hoi Lotte')->toContain('Instagram op professioneel');
});

test('afwijzen vraagt een reden, mailt en sluit verdere besluiten af', function () {
    Mail::fake();
    $lead = ($this->aanmelding)();
    $ruben = ($this->wie)('ruben');

    $this->actingAs($ruben)->post($this->app_."/team/screening/{$lead->id}/afwijzen", ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($ruben)->post($this->app_."/team/screening/{$lead->id}/afwijzen", ['reason' => 'Accounts zijn niet openbaar'])->assertSessionHasNoErrors();

    expect($lead->fresh()->status)->toBe('afgewezen')
        ->and(ScreeningAanmelding::sole()->decision)->toBe('afgewezen')
        ->and(ActivityLog::where('event', 'screening_afgewezen')->sole()->reason)->toBe('Accounts zijn niet openbaar');
    Mail::assertQueued(ScreeningBericht::class, fn ($m) => $m->sleutel === 'c-afgewezen' && $m->vars['reden'] === 'Accounts zijn niet openbaar');

    // Daarna kan er niet alsnog goedgekeurd worden.
    $this->actingAs($ruben)->post($this->app_."/team/screening/{$lead->id}/goedkeuren", ['level' => 'bronze', 'reason' => 'Toch wel'])->assertSessionHasErrors('lead');
    expect(Creator::count())->toBe(2);
});

test('zonder maildienst ziet het team de uitnodigingslink, en daarmee komt de creator in de eigen omgeving', function () {
    config(['mail.default' => 'log']);
    $lead = ($this->aanmelding)();
    $ruben = ($this->wie)('ruben');

    $this->actingAs($ruben)->post($this->app_."/team/screening/{$lead->id}/goedkeuren", ['level' => 'bronze', 'reason' => 'Prima'])
        ->assertRedirect($this->app_."/team/screening/{$lead->id}")->assertSessionHas('uitnodiging');
    $link = session('uitnodiging')['link'];
    expect($link)->toContain('reset-password');

    $this->actingAs($ruben)->get($this->app_."/team/screening/{$lead->id}")->assertInertia(fn ($p) => $p->where('nieuweLink', true));

    // Een nieuwe link maken kan alleen met het recht, en maakt de vorige ongeldig.
    $this->actingAs(($this->wie)('eva'))->post($this->app_."/team/screening/{$lead->id}/uitnodigingslink")->assertForbidden();
    $this->actingAs($ruben)->post($this->app_."/team/screening/{$lead->id}/uitnodigingslink")->assertSessionHas('uitnodiging');
    $nieuw = session('uitnodiging')['link'];
    expect($nieuw)->not->toBe($link);

    // De creator gebruikt de link: wachtwoord kiezen, inloggen en direct de eigen omgeving (geen bevestigingsmail nodig).
    auth()->logout();
    parse_str(parse_url($nieuw, PHP_URL_QUERY), $query);
    $this->post($this->app_.'/reset-password', [
        'token' => basename(parse_url($nieuw, PHP_URL_PATH)), 'email' => $query['email'], 'password' => 'EenSterkWachtwoord!2026', 'password_confirmation' => 'EenSterkWachtwoord!2026',
    ])->assertSessionHasNoErrors();
    $this->post($this->app_.'/login', ['email' => 'lotte@voorbeeld.nl', 'password' => 'EenSterkWachtwoord!2026']);
    $lotte = User::where('email', 'lotte@voorbeeld.nl')->sole();
    $this->assertAuthenticatedAs($lotte);
    expect($lotte->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->get($this->app_.'/creator')->assertOk();
});

test('met een maildienst komt er geen uitnodigingslink in beeld', function () {
    config(['mail.default' => 'smtp']);
    Mail::fake();
    $lead = ($this->aanmelding)();
    $ruben = ($this->wie)('ruben');

    $this->actingAs($ruben)->post($this->app_."/team/screening/{$lead->id}/goedkeuren", ['level' => 'bronze', 'reason' => 'Prima'])
        ->assertSessionMissing('uitnodiging');
    $this->actingAs($ruben)->get($this->app_."/team/screening/{$lead->id}")->assertInertia(fn ($p) => $p->where('nieuweLink', false));
});
