<?php

use App\Models\Collaboration;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoCijfersEnGeldSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-22 10:00'));
    $this->seed(DemoSeeder::class);
    $this->seed(DemoCijfersEnGeldSeeder::class);
    $this->app_url = 'http://app.mebranding.test';
    $this->wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
});

test('het dashboard vraagt statistieken.bekijken', function () {
    $this->actingAs(($this->wie)('ruben'))->get("{$this->app_url}/team/dashboard")->assertForbidden(); // Contentcontrole
    $this->actingAs(($this->wie)('marit'))->get("{$this->app_url}/team/dashboard")->assertForbidden(); // geen team
    $this->actingAs(($this->wie)('eva'))->get("{$this->app_url}/team/dashboard")->assertOk(); // Accountmanager
});

test('kerncijfers kloppen met de demodata', function () {
    // Eén nieuwe aanvraag deze week (maandag 16 tot en met zondag 22 november)
    $bloesem = Company::first();
    Collaboration::create([
        'number' => 'S-NIEUW', 'company_id' => $bloesem->id, 'location_id' => $bloesem->locations()->value('id'),
        'title' => 'Nieuw', 'briefing' => 'x', 'status' => 'nieuw', 'points' => 3, 'creator_level' => 'bronze',
    ]);

    $this->actingAs(($this->wie)('eva'))->get("{$this->app_url}/team/dashboard")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('team/cijfers/dashboard')
            ->where('kerncijfers.aanvragen.nu', 1)
            ->where('kerncijfers.lopend', 2) // nieuw en live
            ->where('kerncijfers.afgerond.nu', 1) // Lunch in Sneek, afgerond op 16 november
            ->where('kerncijfers.oordeel.nu', 5)
            ->where('kerncijfers.oordeel.aantal', 1)
            ->where('kerncijfers.bedrijven.actief', 1)
            ->where('kerncijfers.creators.actief', 2)
            ->where('kerncijfers.opeisbaar.bedrag', 16000)
            ->where('kerncijfers.opeisbaar.vrijdag', '2026-11-27')
            ->where('kerncijfers.tegoed.nu.toegekend', 20)
            ->where('kerncijfers.tegoed.nu.verbruikt', 8) // bezoek Sophie (Elite) in november; Marit kwam eind oktober
            ->where('statussen.9.number', 10)->where('statussen.9.count', 1)
            ->has('statussen', 11)
            ->has('leads_per_week', 8)
            ->where('leads_per_week.7.ondernemer', 1) // 21 november
            ->has('platforms', 2));
});

test('maandrapport voor het team, met afdrukbare versie', function () {
    $bloesem = Company::first();
    $this->actingAs(($this->wie)('eva'))->get("{$this->app_url}/team/maandrapport/{$bloesem->id}/2026-11")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('team/cijfers/maandrapport')
            ->where('rapport.maand', '2026-11')
            ->where('rapport.tellingen.live', 2)
            ->where('rapport.tegoed.toegekend', 20)
            ->where('rapport.tegoed.verbruikt', 8)
            ->where('rapport.publicaties.0.metrics.views.value', 18900));
    $this->actingAs(($this->wie)('eva'))->get("{$this->app_url}/team/maandrapport/{$bloesem->id}/2026-11/afdrukken")->assertOk()->assertSee('Wintersoep en appeltaart');
    $this->actingAs(($this->wie)('ruben'))->get("{$this->app_url}/team/maandrapport/{$bloesem->id}")->assertForbidden();
});
