<?php

use App\Jobs\Geocodeer;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use App\Services\Geocoder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/* Kaart voor het team: vestigingen en aanmeldingen van ondernemers op de kaart via PDOK. */
uses(RefreshDatabase::class);

const APP_KT = 'http://app.mebranding.test';

beforeEach(function () {
    config(['mebranding.team_2fa_verplicht' => false, 'mebranding.geocoderen' => true]);
    $this->pdok = fn () => Http::fake(['api.pdok.nl/*' => function ($request) {
        $q = $request->data()['q'] ?? '';
        if (str_contains($q, 'Nergensstraat')) {
            return Http::response(['response' => ['docs' => []]]);
        }
        $woonplaats = str_contains($request->data()['fq'] ?? '', 'woonplaats');

        return Http::response(['response' => ['docs' => [[
            'type' => $woonplaats ? 'woonplaats' : 'adres',
            'centroide_ll' => $woonplaats ? 'POINT(5.5403 53.1877)' : 'POINT(5.54506267 53.18707864)',
        ]]]]);
    }]);
});

test('PDOK geeft een plek: op het adres, of het midden van de plaats als het adres niet bestaat', function () {
    ($this->pdok)();
    $g = app(Geocoder::class);

    expect($g->zoek('Zilverstraat 3-5', 'Franeker'))->toBe(['lat' => 53.18707864, 'lng' => 5.54506267, 'precisie' => 'adres'])
        ->and($g->zoek('Nergensstraat 1', 'Franeker'))->toBe(['lat' => 53.1877, 'lng' => 5.5403, 'precisie' => 'plaats'])
        ->and($g->zoek(null, null))->toBeNull();
});

test('een nieuwe of verhuisde vestiging en een aanmelding van een ondernemer gaan op de kaart, een creator niet', function () {
    Queue::fake();
    $this->seed(DemoSeeder::class);
    $v = Company::first()->locations()->create(['name' => 'Cheung Kwong', 'address' => 'Zilverstraat 3-5', 'city' => 'Franeker']);
    Queue::assertPushed(Geocodeer::class, fn ($job) => $job->model->is($v));

    Queue::fake();
    $v->update(['experience' => 'Shared dining']);
    Queue::assertNothingPushed();
    $v->update(['address' => 'Voorstraat 1']);
    Queue::assertPushed(Geocodeer::class);

    Queue::fake();
    Lead::create(['type' => Lead::TYPE_ONDERNEMER, 'name' => 'Tai', 'email' => 't@x.test', 'company' => 'Cheung Kwong', 'city' => 'Franeker']);
    Lead::create(['type' => Lead::TYPE_CREATOR, 'name' => 'Eline', 'email' => 'e@x.test', 'city' => 'Leeuwarden']);
    Queue::assertPushed(Geocodeer::class, 1);
});

test('het kennismakingsformulier vraagt de plaats, en de aanmelding komt op de kaart', function () {
    ($this->pdok)();
    $this->post('http://mebranding.test/kennismaken', [
        'naam' => 'Tai Pan', 'email' => 'tai@voorbeeld.test', 'bedrijfsnaam' => 'Cheung Kwong', 'akkoord' => '1',
    ])->assertSessionHasErrors('woonplaats');

    $this->post('http://mebranding.test/kennismaken', [
        'naam' => 'Tai Pan', 'email' => 'tai@voorbeeld.test', 'bedrijfsnaam' => 'Cheung Kwong', 'adres' => 'Zilverstraat 3-5', 'woonplaats' => 'Franeker', 'akkoord' => '1',
    ])->assertSessionHasNoErrors();

    $lead = Lead::where('email', 'tai@voorbeeld.test')->sole();
    expect($lead->city)->toBe('Franeker')->and($lead->data['adres'])->toBe('Zilverstraat 3-5')
        ->and($lead->lat)->toEqualWithDelta(53.1870786, 0.000001)->and($lead->geo_precisie)->toBe('adres'); // queue draait synchroon in tests
});

test('het team ziet de kaart met vestigingen, open klussen en aanmeldingen; zonder recht niet', function () {
    ($this->pdok)();
    $this->seed(DemoSeeder::class);
    $sanne = User::where('email', 'sanne@demo.mebranding.nl')->sole();
    $vestiging = $sanne->companies()->first()->locations()->first();
    $this->actingAs($sanne)->post(APP_KT.'/ondernemer/samenwerkingen', [
        'location_id' => $vestiging->id, 'title' => 'Kerstlunch', 'briefing' => 'Laat de kerstlunch zien.', 'alcohol' => false, 'creator_level' => 'bronze',
    ])->assertSessionHasNoErrors();
    $this->artisan('mebranding:geocoderen')->assertSuccessful();

    $this->actingAs(User::where('email', 'eva@demo.mebranding.nl')->sole())->get(APP_KT.'/team/kaart')->assertOk()
        ->assertInertia(fn ($p) => $p->component('team/kaart')
            ->where('vestigingen', fn ($lijst) => collect($lijst)->contains(fn ($v) => $v['id'] === $vestiging->id && $v['lat'] !== null && count($v['open_klussen']) === 1 && $v['soort'] === 'klant')));

    $this->actingAs(User::where('email', 'marit@demo.mebranding.nl')->sole())->get(APP_KT.'/team/kaart')->assertForbidden();
});
