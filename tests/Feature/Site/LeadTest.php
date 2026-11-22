<?php

use App\Mail\LeadBevestiging;
use App\Mail\NieuweLead;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

const SITE = 'http://mebranding.test';

beforeEach(fn () => Mail::fake());

test('kennismaking ondernemer wordt opgeslagen en gemaild', function () {
    $this->post(SITE.'/voor-ondernemers', [
        'naam' => 'Sanne Hiemstra',
        'email' => 'Sanne@Voorbeeld.nl',
        'telefoon' => '0612345678',
        'bedrijfsnaam' => 'Lunchroom Bloesem',
        'plaats' => 'Leeuwarden',
        'branche' => 'Horeca',
        'bericht' => 'Graag een kennismaking.',
        'akkoord' => '1',
    ])->assertRedirect(SITE.'/voor-ondernemers')->assertSessionHas('aangemeld');

    $lead = Lead::sole();
    expect($lead->type)->toBe('ondernemer')
        ->and($lead->email)->toBe('sanne@voorbeeld.nl')
        ->and($lead->company)->toBe('Lunchroom Bloesem')
        ->and($lead->data)->toBe(['branche' => 'Horeca'])
        ->and($lead->consent_at)->not->toBeNull();

    Mail::assertQueued(NieuweLead::class, fn ($m) => $m->hasTo(config('mebranding.team_email')));
    Mail::assertQueued(LeadBevestiging::class, fn ($m) => $m->hasTo('sanne@voorbeeld.nl'));
});

test('aanmelding creator wordt opgeslagen met extra velden', function () {
    $this->post(SITE.'/voor-creators', [
        'naam' => 'Marit de Vries',
        'email' => 'marit@voorbeeld.nl',
        'telefoon' => '0687654321',
        'woonplaats' => 'Sneek',
        'geboortedatum' => now()->subYears(22)->toDateString(),
        'instagram-naam' => '@marit',
        'niches' => ['Eten en drinken', 'Lifestyle'],
        'akkoord' => '1',
    ])->assertRedirect(SITE.'/voor-creators');

    $lead = Lead::sole();
    expect($lead->type)->toBe('creator')
        ->and($lead->city)->toBe('Sneek')
        ->and($lead->data['niches'])->toBe(['Eten en drinken', 'Lifestyle']);
});

test('creator jonger dan 18 wordt geweigerd', function () {
    $this->post(SITE.'/voor-creators', [
        'naam' => 'Jong', 'email' => 'jong@voorbeeld.nl', 'telefoon' => '06', 'woonplaats' => 'Sneek',
        'geboortedatum' => now()->subYears(16)->toDateString(), 'akkoord' => '1',
    ])->assertSessionHasErrors('geboortedatum');

    expect(Lead::count())->toBe(0);
});

test('zonder akkoord en verplichte velden komen foutmeldingen', function () {
    $this->post(SITE.'/voor-ondernemers', [])
        ->assertSessionHasErrors(['naam', 'email', 'bedrijfsnaam', 'akkoord']);
});

test('honeypot slaat niets op maar geeft wel een gewone bedankpagina', function () {
    $this->post(SITE.'/voor-ondernemers', [
        'naam' => 'Bot', 'email' => 'bot@spam.nl', 'bedrijfsnaam' => 'Spam', 'akkoord' => '1', 'website' => 'http://spam',
    ])->assertRedirect(SITE.'/voor-ondernemers')->assertSessionHas('aangemeld');

    expect(Lead::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('formulieren hebben een limiet per uur', function () {
    config(['mebranding.lead_rate_limit_per_hour' => 2]);
    $data = ['naam' => 'X', 'email' => 'x@voorbeeld.nl', 'bedrijfsnaam' => 'X', 'akkoord' => '1'];

    $this->post(SITE.'/voor-ondernemers', $data)->assertRedirect();
    $this->post(SITE.'/voor-ondernemers', $data)->assertRedirect();
    $this->post(SITE.'/voor-ondernemers', $data)->assertStatus(429);
});

test('te vaak versturen geeft een nette Nederlandse melding', function () {
    config(['mebranding.lead_rate_limit_per_hour' => 1]);

    $this->post(SITE.'/voor-ondernemers', []);
    $this->post(SITE.'/voor-ondernemers', [])->assertStatus(429)->assertSee('Even geduld');
});
