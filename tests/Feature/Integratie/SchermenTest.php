<?php

use App\Models\User;
use Database\Seeders\DemoAcademySeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Rooktest over alle modules heen: elk scherm (GET zonder parameter) van elke omgeving opent zonder fout,
 * voor een gebruiker van die omgeving. Nieuwe modules worden vanzelf meegenomen.
 */
beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoAcademySeeder::class);
    $this->artisan('mebranding:super-admin', ['email' => 'baas@voorbeeld.nl', 'naam' => 'Baas']);
});

dataset('omgevingen', [
    'ondernemer' => ['ondernemer', 'sanne@demo.mebranding.nl'],
    'creator' => ['creator', 'marit@demo.mebranding.nl'],
    'academy' => ['academy', 'lisa@demo.mebranding.nl'],
    'team (super admin)' => ['team', 'baas@voorbeeld.nl'],
]);

test('alle schermen van een omgeving openen zonder fout', function (string $omgeving, string $email) {
    $user = User::where('email', $email)->firstOrFail();
    $paden = collect(Route::getRoutes())
        ->filter(fn ($r) => in_array('GET', $r->methods()) && Str::startsWith($r->getName() ?? '', "{$omgeving}.") && ! Str::contains($r->uri(), '{'))
        ->map(fn ($r) => '/'.ltrim($r->uri(), '/'))
        ->unique()->values();

    expect($paden)->not->toBeEmpty();

    $fouten = $paden->mapWithKeys(fn ($pad) => [$pad => $this->actingAs($user)->get('http://app.mebranding.test'.$pad)->status()])
        ->filter(fn ($status) => ! in_array($status, [200, 302], true));

    expect($fouten->all())->toBe([]);
})->with('omgevingen');
