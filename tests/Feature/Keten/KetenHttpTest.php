<?php

use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const APP = 'http://app.mebranding.test';

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DemoSeeder::class);
    $this->wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
});

test('de keten via de schermen: aanvraag, koppelen, uploaden, controleren, goedkeuren, ondernemer ziet video en melding', function () {
    $sanne = ($this->wie)('sanne');
    $eva = ($this->wie)('eva');
    $ruben = ($this->wie)('ruben');
    $marit = ($this->wie)('marit');
    $bloesem = Company::first();

    // 1. Sanne vraagt aan
    $this->actingAs($sanne)->post(APP.'/ondernemer/samenwerkingen', [
        'location_id' => $bloesem->locations()->first()->id, 'title' => 'Kerstlunch', 'briefing' => 'Laat de kerstlunch zien.',
        'alcohol' => false, 'creator_level' => 'bronze',
    ])->assertSessionHasNoErrors();
    $c = Collaboration::sole();

    // Eva ziet hem in de werkvoorraad en nodigt Marit uit
    $this->actingAs($eva)->get(APP.'/team')->assertOk();
    $this->actingAs($eva)->post(APP."/team/samenwerkingen/{$c->id}/uitnodigen", ['creator_id' => $marit->creator->id])->assertSessionHasNoErrors();

    // Marit tekent, kiest een tijdvak, checkt in, filmt en levert aan
    $uitnodiging = $c->invitations()->first();
    $this->actingAs($marit)->post(APP."/creator/uitnodigingen/{$uitnodiging->id}/accepteren", ['signed_name' => 'Marit Postma', 'akkoord' => '1'])->assertSessionHasNoErrors();
    expect($c->fresh()->status)->toBe(Status::CreatorGevonden);

    $moment = now()->addDay()->setTime(12, 0);
    $this->actingAs($marit)->post(APP."/creator/klussen/{$c->id}/tijdvak", ['moment' => $moment->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
    $this->travelTo($moment);
    $this->actingAs($marit)->post(APP."/creator/klussen/{$c->id}/inchecken")->assertSessionHasNoErrors();
    $this->actingAs($marit)->post(APP."/creator/klussen/{$c->id}/klaar")->assertSessionHasNoErrors();
    $this->actingAs($marit)->post(APP."/creator/klussen/{$c->id}/indienen", [
        'video' => UploadedFile::fake()->create('v1.mp4', 800, 'video/mp4'),
        'caption' => 'Betaalde samenwerking met Lunchroom Bloesem. Proef de kerst!',
        'label_platform' => '1', 'label_beeld' => '1',
    ])->assertSessionHasNoErrors();
    expect($c->fresh()->status)->toBe(Status::TerControle);

    // Sanne kijkt mee (met watermerk, niet downloadbaar)
    $versie = $c->fresh()->latestVersion;
    $this->actingAs($sanne)->get(APP."/video/{$versie->id}")->assertOk();
    expect((string) $this->actingAs($sanne)->get(APP."/video/{$versie->id}?download=1")->headers->get('content-disposition'))->not->toContain('attachment');

    // Ruben keurt goed met publicatiedatum
    $this->actingAs($ruben)->post(APP."/team/samenwerkingen/{$c->id}/goedkeuren", ['publish_on' => now()->addDay()->toDateString()])->assertSessionHasNoErrors();
    expect($c->fresh()->status)->toBe(Status::Goedgekeurd);

    // Sanne heeft een melding en mag de video nu downloaden
    $melding = $sanne->notifications()->get()->firstWhere('data.key', 'o-goedgekeurd');
    expect($melding)->not->toBeNull()->and($melding->data['url'])->toContain("/ondernemer/samenwerkingen/{$c->id}");
    $this->actingAs($sanne)->get(APP."/video/{$versie->id}?download=1")->assertOk()->assertDownload();
});

test('elke omgeving is afgeschermd', function () {
    $marit = ($this->wie)('marit');
    $sanne = ($this->wie)('sanne');

    $this->actingAs($marit)->get(APP.'/team')->assertForbidden();
    $this->actingAs($marit)->get(APP.'/ondernemer')->assertForbidden();
    $this->actingAs($sanne)->get(APP.'/creator')->assertForbidden();
    $this->actingAs($sanne)->get(APP.'/team')->assertForbidden();
});

test('contentcontrole mag geen creators uitnodigen', function () {
    $sanne = ($this->wie)('sanne');
    $bloesem = Company::first();
    $this->actingAs($sanne)->post(APP.'/ondernemer/samenwerkingen', [
        'location_id' => $bloesem->locations()->first()->id, 'title' => 'X', 'briefing' => 'X', 'alcohol' => false, 'creator_level' => 'bronze',
    ]);
    $c = Collaboration::sole();

    $this->actingAs(($this->wie)('ruben'))->post(APP."/team/samenwerkingen/{$c->id}/uitnodigen", ['creator_id' => ($this->wie)('marit')->creator->id])->assertForbidden();
});

test('super admin kan inloggen als een ander en terug, met logregel', function () {
    $this->artisan('mebranding:super-admin', ['email' => 'dave@voorbeeld.nl', 'naam' => 'Dave']);
    $dave = User::where('email', 'dave@voorbeeld.nl')->first();
    $sanne = ($this->wie)('sanne');

    $this->actingAs($dave)->post(APP."/inloggen-als/{$sanne->id}")->assertRedirect(route('start'));
    $this->assertAuthenticatedAs($sanne);
    $this->post(APP.'/terug')->assertRedirect(APP.'/team');
    $this->assertAuthenticatedAs($dave);
    $this->assertDatabaseHas('activity_log', ['event' => 'inloggen_als', 'user_id' => $dave->id]);

    $this->actingAs($sanne)->post(APP."/inloggen-als/{$dave->id}")->assertForbidden();
});
