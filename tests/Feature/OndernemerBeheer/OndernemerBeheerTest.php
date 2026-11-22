<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyOnboarding;
use App\Models\CreditLedger;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PlatformMelding;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->url = fn (string $pad) => 'http://app.mebranding.test'.$pad;
    $this->wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
    $this->bloesem = Company::where('name', 'Lunchroom Bloesem')->first();
    $this->sanne = ($this->wie)('sanne');

    // Een lid van Bloesem
    $this->jorrit = User::create(['name' => 'Jorrit Veenstra', 'email' => 'jorrit@demo.mebranding.nl', 'password' => 'geheim-wachtwoord']);
    $this->jorrit->forceFill(['email_verified_at' => now()])->save();
    $this->jorrit->assignRole('Ondernemer Lid');
    $this->bloesem->users()->attach($this->jorrit->id, ['role' => 'lid']);
});

test('de eigenaar ziet alle beheerschermen', function () {
    foreach (['/ondernemer/vestigingen', '/ondernemer/gebruikers', '/ondernemer/abonnement', '/ondernemer/aan-de-slag'] as $pad) {
        $this->actingAs($this->sanne)->get(($this->url)($pad))->assertOk();
    }
});

test('een lid kijkt mee maar wijzigt geen abonnement en beheert geen gebruikers', function () {
    $this->actingAs($this->jorrit)->get(($this->url)('/ondernemer/abonnement'))->assertOk()
        ->assertInertia(fn ($p) => $p->component('ondernemer/beheer/abonnement')->where('can.wijzigen', false));
    $this->actingAs($this->jorrit)->get(($this->url)('/ondernemer/gebruikers'))->assertOk()
        ->assertInertia(fn ($p) => $p->where('can.beheren', false));

    $this->actingAs($this->jorrit)->post(($this->url)('/ondernemer/abonnement/verzoek'), ['soort' => 'wijzigen', 'plan_id' => 1])->assertForbidden();
    $this->actingAs($this->jorrit)->post(($this->url)('/ondernemer/gebruikers'), ['email' => 'kees@example.nl', 'role' => 'lid'])->assertForbidden();
    $this->actingAs($this->jorrit)->put(($this->url)("/ondernemer/gebruikers/{$this->jorrit->id}"), ['role' => 'eigenaar'])->assertForbidden();
    $this->actingAs($this->jorrit)->delete(($this->url)("/ondernemer/gebruikers/{$this->sanne->id}"))->assertForbidden();
    $this->actingAs($this->jorrit)->put(($this->url)('/ondernemer/bedrijfsinstellingen'), ['self_confirm_visits' => true])->assertForbidden();

    expect(Task::where('type', 'abonnement')->count())->toBe(0)
        ->and($this->bloesem->users()->count())->toBe(2)
        ->and($this->bloesem->fresh()->self_confirm_visits)->toBeFalse();
});

test('uitnodigen maakt een account, koppelt het aan het bedrijf en stuurt de uitnodiging', function () {
    Notification::fake();

    $this->actingAs($this->sanne)->post(($this->url)('/ondernemer/gebruikers'), ['email' => 'Kees@Example.nl', 'role' => 'lid'])
        ->assertSessionHasNoErrors();

    $kees = User::where('email', 'kees@example.nl')->sole();
    expect($this->bloesem->users()->whereKey($kees->id)->first()->pivot->role)->toBe('lid')
        ->and($kees->hasRole('Ondernemer Lid'))->toBeTrue()
        ->and($kees->homePortal())->toBe('ondernemer')
        ->and(ActivityLog::where('event', 'gebruiker_uitgenodigd')->where('subject_id', $this->bloesem->id)->exists())->toBeTrue()
        ->and(CompanyOnboarding::voor($this->bloesem)->team_invited_at)->not->toBeNull();

    Notification::assertSentTo($kees, PlatformMelding::class, fn ($m) => str_contains($m->toArray($kees)['url'] ?? '', 'reset-password'));

    // Nog een keer hetzelfde adres: netjes geweigerd
    $this->actingAs($this->sanne)->post(($this->url)('/ondernemer/gebruikers'), ['email' => 'kees@example.nl', 'role' => 'lid'])
        ->assertSessionHasErrors('email');
});

test('rol wijzigen werkt, maar de laatste eigenaar blijft', function () {
    $this->actingAs($this->sanne)->put(($this->url)("/ondernemer/gebruikers/{$this->sanne->id}"), ['role' => 'lid'])->assertSessionHasErrors('rol');
    $this->actingAs($this->sanne)->delete(($this->url)("/ondernemer/gebruikers/{$this->sanne->id}"))->assertSessionHasErrors('rol');

    $this->actingAs($this->sanne)->put(($this->url)("/ondernemer/gebruikers/{$this->jorrit->id}"), ['role' => 'eigenaar'])->assertSessionHasNoErrors();
    expect($this->jorrit->fresh()->hasRole('Ondernemer Eigenaar'))->toBeTrue()
        ->and($this->jorrit->fresh()->hasRole('Ondernemer Lid'))->toBeFalse();

    // Nu er twee eigenaren zijn, mag Jorrit weer lid worden en daarna weg
    $this->actingAs($this->sanne)->put(($this->url)("/ondernemer/gebruikers/{$this->jorrit->id}"), ['role' => 'lid'])->assertSessionHasNoErrors();
    $this->actingAs($this->sanne)->delete(($this->url)("/ondernemer/gebruikers/{$this->jorrit->id}"))->assertSessionHasNoErrors();
    expect($this->bloesem->users()->whereKey($this->jorrit->id)->exists())->toBeFalse()
        ->and($this->jorrit->fresh()->getRoleNames()->all())->toBe([])
        ->and(User::whereKey($this->jorrit->id)->exists())->toBeTrue();
});

test('een ander bedrijf beheer je niet', function () {
    $ander = Company::create(['name' => 'Bakkerij Anders']);
    $vreemd = $ander->locations()->create(['name' => 'Anders', 'city' => 'Sneek']);
    $buiten = User::create(['name' => 'Buiten', 'email' => 'buiten@example.nl', 'password' => 'x-geheim-123']);
    $ander->users()->attach($buiten->id, ['role' => 'eigenaar']);

    $this->actingAs($this->sanne)->put(($this->url)("/ondernemer/vestigingen/{$vreemd->id}"), ['name' => 'Gekaapt', 'city' => 'Sneek'])->assertNotFound();
    $this->actingAs($this->sanne)->delete(($this->url)("/ondernemer/gebruikers/{$buiten->id}"))->assertNotFound();
});

test('vestiging en bezoektijden opslaan', function () {
    $vestiging = $this->bloesem->locations()->where('name', 'Sneek')->first();

    $this->actingAs($this->jorrit)->put(($this->url)("/ondernemer/vestigingen/{$vestiging->id}"), [
        'name' => 'Bloesem Sneek', 'address' => 'Marktstraat 12', 'city' => 'Sneek', 'experience' => 'Lunch voor 1 persoon.',
        'windows' => [
            ['weekday' => 3, 'starts_at' => '10:00', 'ends_at' => '12:00'],
            ['weekday' => 3, 'starts_at' => '14:00', 'ends_at' => '16:00'],
            ['weekday' => 6, 'starts_at' => '11:00', 'ends_at' => '15:00'],
        ],
    ])->assertSessionHasNoErrors();

    $vestiging->refresh();
    expect($vestiging->name)->toBe('Bloesem Sneek')
        ->and($vestiging->experience)->toBe('Lunch voor 1 persoon.')
        ->and($vestiging->visitWindows()->count())->toBe(3)
        ->and($vestiging->visitWindows->first()->label())->toBe('woensdag 10:00 - 12:00')
        ->and(ActivityLog::where('event', 'vestiging_gewijzigd')->exists())->toBeTrue()
        ->and(CompanyOnboarding::voor($this->bloesem)->locations_checked_at)->not->toBeNull();

    // Overlap en verkeerde volgorde worden geweigerd
    $this->actingAs($this->sanne)->put(($this->url)("/ondernemer/vestigingen/{$vestiging->id}"), [
        'name' => 'Bloesem Sneek', 'city' => 'Sneek',
        'windows' => [['weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '12:00'], ['weekday' => 1, 'starts_at' => '11:00', 'ends_at' => '13:00'], ['weekday' => 2, 'starts_at' => '15:00', 'ends_at' => '14:00']],
    ])->assertSessionHasErrors(['windows.1.starts_at', 'windows.2.ends_at']);
    expect($vestiging->visitWindows()->count())->toBe(3);

    // Nieuwe vestiging
    $this->actingAs($this->sanne)->post(($this->url)('/ondernemer/vestigingen'), [
        'name' => 'Bloesem Harlingen', 'city' => 'Harlingen', 'windows' => [['weekday' => 5, 'starts_at' => '10:00', 'ends_at' => '16:00']],
    ])->assertSessionHasNoErrors();
    expect($this->bloesem->locations()->where('name', 'Bloesem Harlingen')->first()->visitWindows()->count())->toBe(1);
});

test('de eigenaar zet zelf bevestigen en zelf keuren aan', function () {
    $this->actingAs($this->sanne)->put(($this->url)('/ondernemer/bedrijfsinstellingen'), ['self_confirm_visits' => true, 'owner_approves_content' => true])->assertSessionHasNoErrors();

    expect($this->bloesem->fresh()->self_confirm_visits)->toBeTrue()
        ->and($this->bloesem->fresh()->owner_approves_content)->toBeTrue()
        ->and(ActivityLog::where('event', 'bedrijfsinstellingen')->first()->data)->toMatchArray(['self_confirm_visits' => true]);
});

test('verzoek om pakket te wijzigen of op te zeggen maakt een taak en een logregel, zonder iets te wijzigen', function () {
    $regio = Plan::where('name', 'Regio')->first();
    $abonnementen = $this->bloesem->subscriptions()->count();

    $this->actingAs($this->sanne)->post(($this->url)('/ondernemer/abonnement/verzoek'), ['soort' => 'wijzigen', 'plan_id' => $regio->id, 'toelichting' => 'Vierde vestiging erbij'])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->sanne)->post(($this->url)('/ondernemer/abonnement/verzoek'), ['soort' => 'opzeggen'])
        ->assertSessionHasErrors(['reden', 'akkoord']);
    $this->actingAs($this->sanne)->post(($this->url)('/ondernemer/abonnement/verzoek'), ['soort' => 'opzeggen', 'reden' => 'Te duur voor wat het oplevert', 'akkoord' => '1'])
        ->assertSessionHasNoErrors();

    $taken = Task::where('type', 'abonnement')->where('company_id', $this->bloesem->id)->get();
    expect($taken)->toHaveCount(2)
        ->and($taken->first()->permission)->toBe('bedrijven.beheren')
        ->and($taken->first()->title)->toContain('Regio')
        ->and(ActivityLog::where('event', 'abonnement_verzoek')->count())->toBe(2)
        ->and($this->bloesem->subscriptions()->count())->toBe($abonnementen);

    // De accountmanager ziet het in de werkvoorraad en handelt het af
    $eva = ($this->wie)('eva');
    $this->actingAs($eva)->get(($this->url)('/team'))->assertInertia(fn ($p) => $p->where('tasks', fn ($t) => collect($t)->where('type', 'abonnement')->count() === 2));
    $this->actingAs($eva)->post(($this->url)("/team/bedrijven/{$this->bloesem->id}/verzoeken/{$taken->first()->id}"))->assertSessionHasNoErrors();
    expect($taken->first()->fresh()->closed_at)->not->toBeNull();
});

test('tegoedcorrectie door het team boekt in het grootboek met een logregel', function () {
    $eva = ($this->wie)('eva'); // Accountmanager: geen tegoed.corrigeren
    $financieel = User::create(['name' => 'Femke Financieel', 'email' => 'femke@demo.mebranding.nl', 'password' => 'x-geheim-123']);
    $financieel->forceFill(['email_verified_at' => now()])->save();
    $financieel->assignRole('Financieel');
    $saldo = $this->bloesem->availablePoints();

    $this->actingAs($eva)->get(($this->url)("/team/bedrijven/{$this->bloesem->id}"))->assertOk()
        ->assertInertia(fn ($p) => $p->component('team/ondernemer-beheer/bedrijf')->where('can.corrigeren', false)->where('can.beheren', true));
    $this->actingAs($eva)->post(($this->url)("/team/bedrijven/{$this->bloesem->id}/tegoed"), ['points' => 5, 'reason' => 'Compensatie'])->assertForbidden();

    $this->actingAs($financieel)->post(($this->url)("/team/bedrijven/{$this->bloesem->id}/tegoed"), ['points' => 5])->assertSessionHasErrors('reason');
    $this->actingAs($financieel)->post(($this->url)("/team/bedrijven/{$this->bloesem->id}/tegoed"), ['points' => -3, 'reason' => 'Dubbel toegekend in november'])
        ->assertSessionHasNoErrors();

    $boeking = CreditLedger::where('company_id', $this->bloesem->id)->where('kind', CreditLedger::CORRECTIE)->sole();
    $log = ActivityLog::where('event', 'tegoed_correctie')->sole();
    expect($boeking->points)->toBe(-3)
        ->and($boeking->user_id)->toBe($financieel->id)
        ->and($this->bloesem->availablePoints())->toBe($saldo - 3)
        ->and($log->reason)->toBe('Dubbel toegekend in november')
        ->and($log->user_id)->toBe($financieel->id)
        ->and($log->data['punten'])->toBe(-3);
});

test('het team bewerkt bedrijfsgegevens en kiest een accountmanager', function () {
    $eva = ($this->wie)('eva');
    $ruben = ($this->wie)('ruben');

    $this->actingAs($eva)->put(($this->url)("/team/bedrijven/{$this->bloesem->id}"), ['name' => 'Lunchroom Bloesem BV', 'kvk' => '12345678', 'account_manager_id' => $this->sanne->id])
        ->assertSessionHasErrors('account_manager_id');
    $this->actingAs($eva)->put(($this->url)("/team/bedrijven/{$this->bloesem->id}"), ['name' => 'Lunchroom Bloesem BV', 'kvk' => '12345678', 'account_manager_id' => $ruben->id])
        ->assertSessionHasNoErrors();

    expect($this->bloesem->fresh()->name)->toBe('Lunchroom Bloesem BV')
        ->and($this->bloesem->fresh()->account_manager_id)->toBe($ruben->id)
        ->and(ActivityLog::where('event', 'bedrijf_gewijzigd')->exists())->toBeTrue();

    // Ruben (Contentcontrole) heeft geen bedrijven.beheren
    $this->actingAs($ruben)->put(($this->url)("/team/bedrijven/{$this->bloesem->id}"), ['name' => 'X'])->assertForbidden();
});

test('na inloggen komt de ondernemer op het welkomstscherm tot de stappen klaar zijn', function () {
    $this->sanne->update(['password' => 'een-goed-wachtwoord']);

    $this->post(($this->url)('/login'), ['email' => $this->sanne->email, 'password' => 'een-goed-wachtwoord'])
        ->assertRedirect(route('ondernemer.beheer.welkom'));
    auth()->logout();

    foreach (array_keys(CompanyOnboarding::STAPPEN) as $stap) {
        $this->actingAs($this->sanne)->post(($this->url)("/ondernemer/aan-de-slag/{$stap}"))->assertRedirect();
    }
    expect(CompanyOnboarding::voor($this->bloesem)->klaar())->toBeTrue();
    auth()->logout();

    $this->post(($this->url)('/login'), ['email' => $this->sanne->email, 'password' => 'een-goed-wachtwoord'])
        ->assertRedirect(route('dashboard'));
});

test('wie via de uitnodiging een wachtwoord kiest, heeft daarmee zijn e-mailadres bevestigd', function () {
    $this->actingAs($this->sanne)->post(($this->url)('/ondernemer/gebruikers'), ['email' => 'kees@example.nl', 'role' => 'lid'])->assertSessionHasNoErrors();
    auth()->logout();
    $kees = User::where('email', 'kees@example.nl')->sole();
    expect($kees->hasVerifiedEmail())->toBeFalse();

    $token = Password::broker()->createToken($kees);
    $this->post(($this->url)('/reset-password'), ['token' => $token, 'email' => $kees->email, 'password' => 'Een-sterk-wachtwoord-2026!', 'password_confirmation' => 'Een-sterk-wachtwoord-2026!'])
        ->assertSessionHasNoErrors();
    expect($kees->fresh()->hasVerifiedEmail())->toBeTrue();
});
