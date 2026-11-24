<?php

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/* Rode tellers in het teammenu: open taken per pagina, dezelfde taken als de werkvoorraad. */
uses(RefreshDatabase::class);

const APP_MT = 'http://app.mebranding.test';

beforeEach(function () {
    config(['mebranding.team_2fa_verplicht' => false]);
    $this->seed(DemoSeeder::class);
    Task::query()->update(['closed_at' => now()]); // schone lei
    $this->wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->sole();
});

test('een nieuwe aanmelding telt bij Screening en Werkvoorraad, een afgehandelde taak telt niet meer', function () {
    Lead::create(['type' => Lead::TYPE_CREATOR, 'name' => 'Eline Vera van der Linde', 'email' => 'eline@voorbeeld.test']);
    Lead::create(['type' => Lead::TYPE_CREATOR, 'name' => 'Marieke Visser', 'email' => 'marieke@voorbeeld.test']);

    $this->actingAs(($this->wie)('ruben'))->get(APP_MT.'/team')->assertInertia(fn ($p) => $p
        ->where('tellers./team/screening', 2)
        ->where('tellers./team', 2)
        ->missing('tellers./team/samenwerkingen'));

    // Wie het recht niet heeft, ziet de taak ook niet als teller (zelfde regel als de werkvoorraad).
    $this->actingAs(($this->wie)('marit'))->get(APP_MT.'/creator')->assertInertia(fn ($p) => $p->where('tellers', []));

    Task::where('type', 'screening')->first()->update(['closed_at' => now()]);
    $this->actingAs(($this->wie)('ruben'))->get(APP_MT.'/team')->assertInertia(fn ($p) => $p->where('tellers./team/screening', 1));
});

test('taken bij een samenwerking tellen bij Samenwerkingen', function () {
    $sanne = ($this->wie)('sanne');
    $this->actingAs($sanne)->post(APP_MT.'/ondernemer/samenwerkingen', [
        'location_id' => $sanne->companies()->first()->locations()->value('id'), 'title' => 'Kerstlunch', 'briefing' => 'Laat de kerstlunch zien.',
        'alcohol' => false, 'creator_level' => 'bronze',
    ])->assertSessionHasNoErrors(); // opent de taak Creator koppelen

    $this->actingAs(($this->wie)('eva'))->get(APP_MT.'/team')->assertInertia(fn ($p) => $p
        ->where('tellers./team/samenwerkingen', 1)->where('tellers./team', 1));
});
