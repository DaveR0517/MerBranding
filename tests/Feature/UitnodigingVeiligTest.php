<?php

use App\Models\Collaboration;
use App\Models\Company;
use App\Models\User;
use App\Notifications\PlatformMelding;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

const APP_UV = 'http://app.mebranding.test';

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    // Zonder maildienst: dan zou een wachtwoordlink in beeld komen (flash 'uitnodiging').
    config(['mail.default' => 'log']);
    $this->wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
    $this->bloesem = Company::where('name', 'Lunchroom Bloesem')->first();
    $this->sanne = ($this->wie)('sanne');
    $this->eva = ($this->wie)('eva');
    $this->tokens = fn (string $email) => DB::table('password_reset_tokens')->where('email', $email)->count();
});

/** Een tweede zaak met een eigen eigenaar, die Sanne van Bloesem (een actief account) als lid probeert toe te voegen. */
function andereZaak(): array
{
    $piet = User::create(['name' => 'Piet Smit', 'email' => 'piet@voorbeeld.nl', 'password' => 'geheim-wachtwoord']);
    $piet->forceFill(['email_verified_at' => now()])->save();
    $piet->assignRole('Ondernemer Eigenaar');
    $zaak = Company::create(['name' => 'Bakkerij Smit', 'email' => 'piet@voorbeeld.nl']);
    $zaak->users()->attach($piet->id, ['role' => 'eigenaar']);

    return [$zaak, $piet];
}

test('een bestaand actief account uitnodigen geeft geen wachtwoordlink en geen link in beeld', function () {
    Notification::fake();
    [$zaak, $piet] = andereZaak();

    $this->actingAs($piet)->post(APP_UV.'/ondernemer/gebruikers', ['email' => $this->sanne->email, 'role' => 'lid'])
        ->assertSessionHasNoErrors()->assertSessionMissing('uitnodiging');

    expect(($this->tokens)($this->sanne->email))->toBe(0)
        ->and($zaak->users()->whereKey($this->sanne->id)->first()->pivot->role)->toBe('lid');
    Notification::assertSentTo($this->sanne, PlatformMelding::class, fn ($m) => $m->template->key === 'g-toegevoegd'
        && $m->url === route('login') && ! str_contains($m->url, 'reset-password'));
    Notification::assertNotSentTo($this->sanne, PlatformMelding::class, fn ($m) => $m->template->key === 'g-uitnodiging');

    // Opnieuw uitnodigen maakt voor een account in gebruik ook geen link.
    $this->actingAs($piet)->post(APP_UV."/ondernemer/gebruikers/{$this->sanne->id}/opnieuw")->assertSessionHasErrors('email');
    expect(($this->tokens)($this->sanne->email))->toBe(0);
});

test('een account dat ooit inlogde maar het e-mailadres niet bevestigde, telt ook als in gebruik', function () {
    $user = User::create(['name' => 'Kees', 'email' => 'kees@voorbeeld.nl', 'password' => 'geheim-wachtwoord']);
    expect($user->inGebruik())->toBeFalse();
    $user->forceFill(['apparaten' => ['x' => ['apparaat' => 'Chrome op Windows']]])->save();
    expect($user->fresh()->inGebruik())->toBeTrue();
});

test('een nieuw account krijgt wel een wachtwoordlink, ook in beeld', function () {
    Notification::fake();

    $this->actingAs($this->sanne)->post(APP_UV.'/ondernemer/gebruikers', ['email' => 'nieuw@voorbeeld.nl', 'role' => 'lid'])
        ->assertSessionHasNoErrors()->assertSessionHas('uitnodiging');

    $nieuw = User::where('email', 'nieuw@voorbeeld.nl')->sole();
    expect(($this->tokens)($nieuw->email))->toBe(1)->and(session('uitnodiging')['link'])->toContain('reset-password');
    Notification::assertSentTo($nieuw, PlatformMelding::class, fn ($m) => $m->template->key === 'g-uitnodiging' && str_contains($m->url, 'reset-password'));
});

test('een nog nooit geactiveerd account opnieuw uitnodigen geeft wel een verse link', function () {
    Notification::fake();
    $this->actingAs($this->sanne)->post(APP_UV.'/ondernemer/gebruikers', ['email' => 'nieuw@voorbeeld.nl', 'role' => 'lid'])->assertSessionHasNoErrors();
    $nieuw = User::where('email', 'nieuw@voorbeeld.nl')->sole();
    $eerste = DB::table('password_reset_tokens')->where('email', $nieuw->email)->value('token');

    // Het team mailt opnieuw (knop "Uitnodiging opnieuw mailen"): verse link, ook in beeld zonder maildienst.
    $this->actingAs($this->eva)->post(APP_UV."/team/bedrijven/{$this->bloesem->id}/gebruikers/{$nieuw->id}/opnieuw")
        ->assertSessionHasNoErrors()->assertSessionHas('uitnodiging');
    expect(DB::table('password_reset_tokens')->where('email', $nieuw->email)->value('token'))->not->toBe($eerste);

    // De ondernemer mailt opnieuw: wel een verse link per mail, maar niet in beeld (het account is niet in deze aanvraag nieuw).
    $this->actingAs($this->sanne)->post(APP_UV."/ondernemer/gebruikers/{$nieuw->id}/opnieuw")
        ->assertSessionHasNoErrors()->assertSessionMissing('uitnodiging');
    Notification::assertSentToTimes($nieuw, PlatformMelding::class, 3);
});

test('een ondernemer ziet nooit de link van andermans ongebruikte account', function () {
    [, $piet] = andereZaak();
    $ongebruikt = User::create(['name' => 'Lotte', 'email' => 'lotte@voorbeeld.nl', 'password' => 'geheim-wachtwoord']);

    $this->actingAs($piet)->post(APP_UV.'/ondernemer/gebruikers', ['email' => $ongebruikt->email, 'role' => 'lid'])
        ->assertSessionHasNoErrors()->assertSessionMissing('uitnodiging');
    $this->actingAs($piet)->post(APP_UV."/ondernemer/gebruikers/{$ongebruikt->id}/opnieuw")
        ->assertSessionHasNoErrors()->assertSessionMissing('uitnodiging');
});

test('een eigenaar wordt door een uitnodiging nooit in rol verlaagd', function () {
    Notification::fake();
    [$zaak, $piet] = andereZaak();

    // Sanne is eigenaar van Bloesem; Piet voegt haar als lid toe aan zijn zaak.
    $this->actingAs($piet)->post(APP_UV.'/ondernemer/gebruikers', ['email' => $this->sanne->email, 'role' => 'lid'])->assertSessionHasNoErrors();
    expect($this->bloesem->users()->whereKey($this->sanne->id)->first()->pivot->role)->toBe('eigenaar')
        ->and($this->sanne->fresh()->hasRole('Ondernemer Eigenaar'))->toBeTrue();

    // Binnen haar eigen zaak kan een uitnodiging haar ook niet tot lid maken (het team niet, een andere eigenaar niet).
    $this->actingAs($this->eva)->post(APP_UV."/team/bedrijven/{$this->bloesem->id}/gebruikers", ['email' => $this->sanne->email, 'role' => 'lid'])
        ->assertSessionHasErrors('email');
    expect($this->bloesem->users()->whereKey($this->sanne->id)->first()->pivot->role)->toBe('eigenaar')
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);
});

test('een bedrijf aanmaken met het adres van een bestaand account stuurt geen wachtwoordlink', function () {
    Notification::fake();
    $this->actingAs($this->eva)->post(APP_UV.'/team/bedrijven', [
        'name' => 'Tweede zaak', 'owner_name' => 'Sanne', 'owner_email' => $this->sanne->email,
        'location_name' => 'Centrum', 'location_city' => 'Leeuwarden', 'plan_id' => 1,
    ])->assertSessionHasNoErrors()->assertSessionMissing('uitnodiging');

    expect(($this->tokens)($this->sanne->email))->toBe(0);
    Notification::assertSentTo($this->sanne, PlatformMelding::class, fn ($m) => $m->template->key === 'g-toegevoegd');
});

test('teamschermen volgen het recht: Sales ziet geen samenwerkingen of logboek', function () {
    $mirthe = ($this->wie)('mirthe'); // Sales
    $ruben = ($this->wie)('ruben'); // Contentcontrole: samenwerkingen, geen logboek
    $this->actingAs($this->sanne)->post(APP_UV.'/ondernemer/samenwerkingen', [
        'location_id' => $this->bloesem->locations()->first()->id, 'title' => 'Proef', 'briefing' => 'Laat de lunch zien.',
        'alcohol' => false, 'creator_level' => 'bronze',
    ])->assertSessionHasNoErrors();
    $c = Collaboration::sole();

    $this->actingAs($mirthe)->get(APP_UV.'/team/samenwerkingen')->assertForbidden();
    $this->actingAs($mirthe)->get(APP_UV."/team/samenwerkingen/{$c->id}")->assertForbidden();
    $this->actingAs($mirthe)->get(APP_UV.'/team/logboek')->assertForbidden();

    $this->actingAs($ruben)->get(APP_UV.'/team/samenwerkingen')->assertOk();
    $this->actingAs($ruben)->get(APP_UV."/team/samenwerkingen/{$c->id}")->assertOk();
    $this->actingAs($ruben)->get(APP_UV.'/team/logboek')->assertForbidden();
    $this->actingAs($this->eva)->get(APP_UV.'/team/logboek')->assertOk();

    // Het menu gebruikt dezelfde rechten.
    $this->actingAs($mirthe)->get(APP_UV.'/team')->assertInertia(fn ($p) => $p
        ->where('auth.permissions', fn ($r) => ! collect($r)->contains('samenwerkingen.bekijken') && ! collect($r)->contains('logboek.bekijken')));
});

test('radar hoort bij wie de taak radar_interesse krijgt (sales.beheren)', function () {
    $this->actingAs(($this->wie)('ruben'))->get(APP_UV.'/team/content/radar')->assertForbidden();
    $this->actingAs($this->eva)->get(APP_UV.'/team/content/radar')->assertForbidden();
    $this->actingAs(($this->wie)('mirthe'))->get(APP_UV.'/team/content/radar')->assertOk();
});

test('een Super Admin mag alle teamschermen', function () {
    $admin = tap(User::factory()->create())->assignRole('Super Admin');
    foreach (['/team/samenwerkingen', '/team/logboek', '/team/content/radar'] as $pad) {
        $this->actingAs($admin)->get(APP_UV.$pad)->assertOk();
    }
});
