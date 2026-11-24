<?php

use App\Models\Creator;
use App\Models\User;
use App\Services\Beschikbaarheid;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/* "Ik ben beschikbaar" (creatorkant van de kaart). Staat standaard uit: config mebranding.creator_kaart. */
uses(RefreshDatabase::class);

const APP_BS = 'http://app.mebranding.test';

beforeEach(function () {
    config(['mebranding.team_2fa_verplicht' => false]);
    $this->seed(DemoSeeder::class);
    $this->sophie = User::where('email', 'sophie@demo.mebranding.nl')->sole();
});

test('zolang de creatorkant uit staat, bestaat beschikbaar niet voor creators', function () {
    expect(config('mebranding.creator_kaart'))->toBeFalse();
    $this->actingAs($this->sophie)->get(APP_BS.'/creator')->assertInertia(fn ($p) => $p->where('beschikbaarheid', null));
    $this->actingAs($this->sophie)->post(APP_BS.'/creator/beschikbaar', ['duur' => '2'])->assertNotFound();
    $this->actingAs(User::where('email', 'eva@demo.mebranding.nl')->sole())->get(APP_BS.'/team/kaart')->assertInertia(fn ($p) => $p->where('creators', []));
});

test('beschikbaar met plek: afgerond op 100 meter, zichtbaar op de teamkaart, en na afloop gewist', function () {
    config(['mebranding.creator_kaart' => true]);
    $this->travelTo(now('Europe/Amsterdam')->setTime(14, 0));

    $this->actingAs($this->sophie)->post(APP_BS.'/creator/beschikbaar', ['duur' => '2', 'lat' => 53.201234, 'lng' => 5.799987])->assertSessionHasNoErrors();
    $c = $this->sophie->creator->fresh();
    expect($c->beschikbaar_lat)->toEqual(53.201)->and($c->beschikbaar_lng)->toEqual(5.8)
        ->and($c->beschikbaar_tot->diffInMinutes(now()->addHours(2)))->toBeLessThan(1);

    $this->actingAs($this->sophie)->get(APP_BS.'/creator')->assertInertia(fn ($p) => $p->where('beschikbaarheid.actief', true)->where('beschikbaarheid.plek', true));
    $this->actingAs(User::where('email', 'eva@demo.mebranding.nl')->sole())->get(APP_BS.'/team/kaart')
        ->assertInertia(fn ($p) => $p->where('creators.0.naam', $this->sophie->name)->where('creators.0.lat', 53.201));

    // Buiten Nederland weigeren.
    $this->actingAs($this->sophie)->post(APP_BS.'/creator/beschikbaar', ['duur' => '2', 'lat' => 48.8, 'lng' => 2.3])->assertSessionHasErrors('lat');

    $this->travel(3)->hours();
    expect(app(Beschikbaarheid::class)->opruimen())->toBe(1);
    $c = $c->fresh();
    expect($c->beschikbaar_tot)->toBeNull()->and($c->beschikbaar_lat)->toBeNull()->and($c->beschikbaar_lng)->toBeNull();
});

test('zonder plek (geen toestemming) en de rest van de dag; stoppen wist alles', function () {
    config(['mebranding.creator_kaart' => true]);
    $this->travelTo(now('Europe/Amsterdam')->setTime(15, 30));

    $this->actingAs($this->sophie)->post(APP_BS.'/creator/beschikbaar', ['duur' => 'dag'])->assertSessionHasNoErrors();
    $c = $this->sophie->creator->fresh();
    expect($c->beschikbaar_tot->setTimezone('Europe/Amsterdam')->format('H:i'))->toBe('22:00')->and($c->beschikbaar_lat)->toBeNull();

    $this->actingAs($this->sophie)->post(APP_BS.'/creator/beschikbaar/stop')->assertSessionHasNoErrors();
    expect(Creator::find($c->id)->beschikbaar_tot)->toBeNull();
});
