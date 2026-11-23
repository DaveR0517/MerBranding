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
    $this->post(SITE.'/kennismaken', [
        'naam' => 'Sanne Hiemstra',
        'email' => 'Sanne@Voorbeeld.nl',
        'telefoon' => '0612345678',
        'bedrijfsnaam' => 'Lunchroom Bloesem',
        'branche' => 'Restaurant of lunchroom',
        'vestigingen' => '2 tot 3',
        'pakket' => 'groei',
        'bericht' => 'Ons nieuwe terras.',
        'akkoord' => '1',
    ])->assertRedirect(SITE.'/kennismaken')->assertSessionHas('aangemeld');

    $lead = Lead::sole();
    expect($lead->type)->toBe('ondernemer')
        ->and($lead->email)->toBe('sanne@voorbeeld.nl')
        ->and($lead->phone)->toBe('0612345678')
        ->and($lead->company)->toBe('Lunchroom Bloesem')
        ->and($lead->message)->toBe('Ons nieuwe terras.')
        ->and($lead->data)->toBe(['branche' => 'Restaurant of lunchroom', 'vestigingen' => '2 tot 3', 'pakket' => 'groei'])
        ->and($lead->consent_at)->not->toBeNull();

    Mail::assertQueued(NieuweLead::class, fn ($m) => $m->hasTo(config('mebranding.team_email')));
    Mail::assertQueued(LeadBevestiging::class, fn ($m) => $m->hasTo('sanne@voorbeeld.nl'));
});

test('aanmelding creator wordt opgeslagen met extra velden', function () {
    $this->post(SITE.'/aanmelden-creator', [
        'voornaam' => 'Marit',
        'achternaam' => 'de Vries',
        'email' => 'marit@voorbeeld.nl',
        'geboortedatum' => now()->subYears(22)->toDateString(),
        'instagram-naam' => '@marit',
        'niche' => 'food-lifestyle',
        'woonplaats' => 'Sneek',
        'bericht' => 'Ik film graag eten.',
        'akkoord' => '1',
    ])->assertRedirect(SITE.'/aanmelden-creator')->assertSessionHas('aangemeld');

    $lead = Lead::sole();
    expect($lead->type)->toBe('creator')
        ->and($lead->name)->toBe('Marit de Vries')
        ->and($lead->city)->toBe('Sneek')
        ->and($lead->message)->toBe('Ik film graag eten.')
        ->and($lead->data['instagram-naam'])->toBe('@marit')
        ->and($lead->data['niches'])->toBe(['eten', 'lifestyle'])
        ->and($lead->data)->not->toHaveKeys(['niche', 'voornaam', 'achternaam']);
});

test('creator jonger dan 18 wordt geweigerd', function () {
    $this->post(SITE.'/aanmelden-creator', [
        'voornaam' => 'Jong', 'achternaam' => 'Mens', 'email' => 'jong@voorbeeld.nl', 'woonplaats' => 'Sneek',
        'geboortedatum' => now()->subYears(16)->toDateString(), 'akkoord' => '1',
    ])->assertSessionHasErrors('geboortedatum');

    expect(Lead::count())->toBe(0);
});

test('zonder akkoord en verplichte velden komen foutmeldingen', function () {
    $this->post(SITE.'/kennismaken', [])
        ->assertSessionHasErrors(['naam', 'email', 'bedrijfsnaam', 'akkoord']);
    $this->post(SITE.'/aanmelden-creator', [])
        ->assertSessionHasErrors(['voornaam', 'achternaam', 'email', 'geboortedatum', 'woonplaats', 'akkoord']);
});

test('foutmeldingen en oude waarden staan bij het veld, na versturen volgt de bedankmelding', function () {
    $this->from(SITE.'/kennismaken')->followingRedirects()->post(SITE.'/kennismaken', ['naam' => 'Sanne', 'email' => 'geen-adres'])
        ->assertOk()
        ->assertSee('Er ontbreekt nog iets')
        ->assertSee('Vul een geldig e-mailadres in.')
        ->assertSee('value="Sanne"', false)
        ->assertSee('aria-describedby="fout-bedrijfsnaam"', false);

    $this->followingRedirects()->post(SITE.'/aanmelden-creator', [
        'voornaam' => 'Lotte', 'achternaam' => 'Kramer', 'email' => 'lotte@voorbeeld.nl', 'woonplaats' => 'Harlingen',
        'geboortedatum' => now()->subYears(25)->toDateString(), 'akkoord' => '1',
    ])->assertOk()->assertSee('je aanmelding is binnen')->assertDontSee('name="voornaam"', false);
});

test('alle paginas van de website werken en verwijzen naar elkaar', function (string $pad, string $tekst) {
    $this->get(SITE.$pad)->assertOk()->assertSee($tekst)
        ->assertSee(route('site.kennismaken'), false)
        ->assertSee(route('site.privacy'), false)
        ->assertSee(route('login'), false)
        ->assertDontSee('.html"', false)
        ->assertDontSee('fonts.googleapis.com', false);
})->with([
    ['/', 'Laat je zaak zien'],
    ['/hoe-het-werkt', 'Hoe het werkt'],
    ['/pakketten', 'Pakketten'],
    ['/diamond', 'Diamond'],
    ['/creators', 'Voor creators'],
    ['/academy', 'Academy'],
    ['/kennismaken', 'Plan een kennismaking'],
    ['/aanmelden-creator', 'Word creator bij MEBRANDING'],
    ['/privacy', 'Concept, versie 0.1'],
    ['/cookies', 'Concept, versie 0.1'],
]);

test('inloggen gaat naar het portaal en oude adressen sturen door', function () {
    $this->get(SITE.'/inloggen')->assertRedirect(route('login'))->assertStatus(302);
    $this->get(SITE.'/voor-ondernemers')->assertRedirect(SITE.'/kennismaken')->assertStatus(301);
    $this->get(SITE.'/voor-creators')->assertRedirect(SITE.'/aanmelden-creator')->assertStatus(301);
});

test('een pakket uit de pakketpagina gaat mee in de aanvraag', function () {
    $this->get(SITE.'/kennismaken?pakket=groei')->assertSee('name="pakket" value="groei"', false);
    $this->get(SITE.'/kennismaken?pakket=onzin')->assertDontSee('name="pakket"', false);
});

test('honeypot slaat niets op maar geeft wel een gewone bedankpagina', function () {
    $this->post(SITE.'/kennismaken', [
        'naam' => 'Bot', 'email' => 'bot@spam.nl', 'bedrijfsnaam' => 'Spam', 'akkoord' => '1', 'website' => 'http://spam',
    ])->assertRedirect(SITE.'/kennismaken')->assertSessionHas('aangemeld');

    expect(Lead::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('formulieren hebben een limiet per uur', function () {
    config(['mebranding.lead_rate_limit_per_hour' => 2]);
    $data = ['naam' => 'X', 'email' => 'x@voorbeeld.nl', 'bedrijfsnaam' => 'X', 'akkoord' => '1'];

    $this->post(SITE.'/kennismaken', $data)->assertRedirect();
    $this->post(SITE.'/kennismaken', $data)->assertRedirect();
    $this->post(SITE.'/kennismaken', $data)->assertStatus(429);
});

test('te vaak versturen geeft een nette Nederlandse melding', function () {
    config(['mebranding.lead_rate_limit_per_hour' => 1]);

    $this->post(SITE.'/kennismaken', []);
    $this->post(SITE.'/kennismaken', [])->assertStatus(429)->assertSee('Even geduld');
});
