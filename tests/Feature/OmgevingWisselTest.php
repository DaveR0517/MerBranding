<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/* Een teamlid met een eigen zaak (Taipan, Dave en Cheung Kwong Franeker) wisselt tussen team en ondernemer. */
uses(RefreshDatabase::class);

const APP_OW = 'http://app.mebranding.test';

beforeEach(function () {
    config(['mebranding.team_2fa_verplicht' => false]);
    $this->seed(DemoSeeder::class);
    $this->zaak = Company::create(['name' => 'Cheung Kwong Franeker']);
    $this->teamlid = User::where('email', 'eva@demo.mebranding.nl')->sole();
    $this->zaak->users()->attach($this->teamlid->id, ['role' => 'eigenaar']);
});

test('een teamlid met een eigen zaak ziet de wissel en het menu volgt de gekozen omgeving', function () {
    $this->actingAs($this->teamlid)->get(APP_OW.'/team')->assertInertia(fn ($p) => $p
        ->where('auth.portal', 'team')
        ->where('auth.omgevingen.0.key', 'team')
        ->where('auth.omgevingen.1.key', 'ondernemer')
        ->where('auth.omgevingen.1.naam', 'Cheung Kwong Franeker'));

    $this->actingAs($this->teamlid)->get(APP_OW.'/ondernemer')->assertOk()->assertInertia(fn ($p) => $p->where('auth.portal', 'ondernemer'));

    // Op een gedeelde pagina blijft het menu bij de laatst gekozen omgeving.
    $this->actingAs($this->teamlid)->get(APP_OW.'/meldingen')->assertInertia(fn ($p) => $p->where('auth.portal', 'ondernemer'));
    $this->actingAs($this->teamlid)->get(APP_OW.'/team')->assertInertia(fn ($p) => $p->where('auth.portal', 'team'));

    // Na inloggen begint een teamlid in het team.
    expect($this->teamlid->homePortal())->toBe('team');
});

test('wie maar een omgeving heeft, ziet geen wissel; de academy telt voor een creator niet mee', function () {
    $sanne = User::where('email', 'sanne@demo.mebranding.nl')->sole();
    $this->actingAs($sanne)->get(APP_OW.'/ondernemer')->assertInertia(fn ($p) => $p->where('auth.portal', 'ondernemer')->where('auth.omgevingen', []));

    $sophie = User::where('email', 'sophie@demo.mebranding.nl')->sole();
    expect($sophie->omgevingen())->toBe(['creator'])->and($sophie->magOmgeving('academy'))->toBeTrue();
    $this->actingAs($sophie)->get(APP_OW.'/creator')->assertInertia(fn ($p) => $p->where('auth.omgevingen', []));

    // Zonder zaak geen toegang tot de ondernemerskant.
    $this->actingAs(User::where('email', 'ruben@demo.mebranding.nl')->sole())->get(APP_OW.'/ondernemer')->assertForbidden();
});

test('een creator die ook in het team komt (Eline) wisselt tussen team en creator met hetzelfde account', function () {
    config(['mail.default' => 'log']);
    $eline = User::where('email', 'sophie@demo.mebranding.nl')->sole(); // een goedgekeurde creator
    $beheerder = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first()
        ?? tap(User::factory()->create())->assignRole('Super Admin');

    $this->actingAs($beheerder)->post(APP_OW.'/team/medewerkers', ['name' => $eline->name, 'email' => $eline->email, 'rollen' => ['Contentcontrole']])
        ->assertSessionHasNoErrors()->assertSessionMissing('uitnodiging'); // bestaand account: geen nieuwe wachtwoordlink

    $eline->refresh();
    expect($eline->omgevingen())->toBe(['team', 'creator'])->and($eline->homePortal())->toBe('team');
    $this->actingAs($eline)->get(APP_OW.'/creator')->assertOk()->assertInertia(fn ($p) => $p
        ->where('auth.portal', 'creator')->where('auth.omgevingen.0.key', 'team')->where('auth.omgevingen.1.naam', 'Creator'));
    $this->actingAs($eline)->get(APP_OW.'/team')->assertInertia(fn ($p) => $p->where('auth.portal', 'team'));
});
