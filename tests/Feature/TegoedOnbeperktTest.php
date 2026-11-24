<?php

use App\Models\Collaboration;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/* Uitzondering voor een eigen zaak (Cheung Kwong Franeker): geen abonnement, aanvragen zonder puntenlimiet. */
uses(RefreshDatabase::class);

const APP_TO = 'http://app.mebranding.test';

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->maak = function (bool $onbeperkt): array {
        $zaak = Company::create(['name' => $onbeperkt ? 'Cheung Kwong Franeker' : 'Gewone zaak']);
        $zaak->forceFill(['onbeperkt_tegoed' => $onbeperkt])->save();
        $vestiging = $zaak->locations()->create(['name' => $zaak->name, 'city' => 'Franeker']);
        $eigenaar = User::factory()->create();
        $zaak->users()->attach($eigenaar->id, ['role' => 'eigenaar']);
        $eigenaar->assignRole('Ondernemer Eigenaar');

        return [$zaak, $vestiging, $eigenaar];
    };
    $this->aanvraag = fn ($eigenaar, $vestiging) => $this->actingAs($eigenaar)->post(APP_TO.'/ondernemer/samenwerkingen', [
        'location_id' => $vestiging->id, 'title' => 'Unlimited Shared Dining + 2 drankjes', 'briefing' => 'Onbeperkt delen.', 'alcohol' => true, 'creator_level' => 'bronze',
    ]);
});

test('een eigen zaak met onbeperkt tegoed vraagt aan zonder punten, het verbruik wordt wel geboekt', function () {
    [$zaak, $vestiging, $eigenaar] = ($this->maak)(true);
    expect($zaak->subscriptions()->exists())->toBeFalse()->and($zaak->availablePoints())->toBe(0);

    $this->actingAs($eigenaar)->get(APP_TO.'/ondernemer/aanvragen')->assertInertia(fn ($p) => $p->where('unlimited_credit', true)->where('available_points', 0));
    $this->actingAs($eigenaar)->get(APP_TO.'/ondernemer')->assertInertia(fn ($p) => $p->where('company.unlimited_credit', true));

    ($this->aanvraag)($eigenaar, $vestiging)->assertSessionHasNoErrors();
    ($this->aanvraag)($eigenaar, $vestiging)->assertSessionHasNoErrors();

    expect(Collaboration::where('company_id', $zaak->id)->count())->toBe(2)
        ->and($zaak->fresh()->availablePoints())->toBe(-6); // 2 x Bronze gereserveerd, zichtbaar als verbruik
});

test('een gewone zaak zonder punten wordt nog steeds tegengehouden', function () {
    [$zaak, $vestiging, $eigenaar] = ($this->maak)(false);

    ($this->aanvraag)($eigenaar, $vestiging)->assertSessionHasErrors('creator_level');
    expect(Collaboration::where('company_id', $zaak->id)->exists())->toBeFalse();
});
