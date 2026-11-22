<?php

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('portaal stuurt gasten naar inloggen', function () {
    $this->get('http://app.mebranding.test/')->assertRedirect('http://app.mebranding.test/dashboard');
    $this->get('http://app.mebranding.test/dashboard')->assertRedirect('http://app.mebranding.test/login');
    $this->get('http://app.mebranding.test/login')->assertOk();
});

test('inloggen staat niet op het websitedomein en registreren bestaat niet', function () {
    $this->get('http://mebranding.test/login')->assertNotFound();
    $this->get('http://app.mebranding.test/register')->assertNotFound();
});

test('super admin commando maakt account met rol en resetlink', function () {
    $this->artisan('mebranding:super-admin', ['email' => 'Dave@Voorbeeld.nl', 'naam' => 'Dave'])
        ->expectsOutputToContain('Super Admin klaar: dave@voorbeeld.nl')
        ->assertSuccessful();

    $user = User::where('email', 'dave@voorbeeld.nl')->sole();
    expect($user->hasRole('Super Admin'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->can('team.beheren'))->toBeTrue();
});

test('rechtensjablonen geven alleen hun eigen rechten', function () {
    $this->seed(RolesSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('Contentcontrole');

    expect($user->can('content.controleren'))->toBeTrue()
        ->and($user->can('financieel.beheren'))->toBeFalse();
});

test('op één testadres staat het portaal onder APP_PREFIX', function () {
    $proces = new Symfony\Component\Process\Process(
        [PHP_BINARY, 'artisan', 'route:list', '--json'],
        base_path(),
        ['SITE_DOMAIN' => 'mebranding.on-forge.com', 'APP_DOMAIN' => 'mebranding.on-forge.com', 'APP_PREFIX' => 'app'],
    );
    $proces->mustRun();
    $routes = collect(json_decode($proces->getOutput(), true))->keyBy('name');

    expect($routes['site.home']['uri'])->toBe('/')
        ->and($routes['login']['uri'])->toBe('app/login')
        ->and($routes['dashboard']['uri'])->toBe('app/dashboard')
        ->and($routes['site.home']['domain'])->toBe('mebranding.on-forge.com');
});
