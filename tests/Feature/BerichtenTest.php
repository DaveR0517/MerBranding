<?php

use App\Models\MessageTemplate;
use App\Models\PushAbonnement;
use App\Models\User;
use App\Notifications\Kanalen\WebpushKanaal;
use App\Notifications\PlatformMelding;
use App\Services\Dagoverzicht;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/* Kanaalregel (besluit Dave 25-11-2026): vaste zaken per mail, tijdgevoelig als push met mail als vangnet, teamsignalen in het dagoverzicht. */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MessageTemplateSeeder::class);
    $this->user = User::factory()->create(['name' => 'Sophie de Vries']);
    $this->melding = fn (string $key) => new PlatformMelding(MessageTemplate::where('key', $key)->sole(), ['zaak' => 'Lunchroom Bloesem', 'voornaam' => 'Sophie'], 'https://mebranding.nl/app');
});

test('vaste zaken gaan altijd per mail, ook met pushmeldingen aan', function () {
    PushAbonnement::create(['user_id' => $this->user->id, 'endpoint' => 'https://push.example/1', 'public_key' => 'a', 'auth_token' => 'b']);

    expect(($this->melding)('c-bezoek-vast')->via($this->user->fresh()))->toContain('mail', 'database', WebpushKanaal::class);
});

test('vangnet: zonder push per mail, met push alleen push en portaal', function () {
    expect(($this->melding)('c-deadline')->via($this->user))->toBe(['database', 'mail']);

    PushAbonnement::create(['user_id' => $this->user->id, 'endpoint' => 'https://push.example/1', 'public_key' => 'a', 'auth_token' => 'b']);
    expect(($this->melding)('c-deadline')->via($this->user->fresh()))->toBe(['database', WebpushKanaal::class]);
});

test('teamsignalen met samenvatting en berichten zonder mail gaan niet los per mail', function () {
    expect(($this->melding)('t-zoeken-lang')->via($this->user))->not->toContain('mail')
        ->and(($this->melding)('c-beoordeling')->via($this->user))->not->toContain('mail');
});

test('elke mailvorm rendert met de voorbeeldwaarden en zonder losse {variabelen}', function () {
    $bron = collect(json_decode(file_get_contents(database_path('data/berichtsjablonen.json')), true)['sjablonen'])->keyBy('id');

    foreach (['o-bezoek-vast', 'c-welkom', 'o-factuur', 'g-beveiliging', 'o-welkom', 'c-feedback'] as $key) {
        $vars = array_map('strval', $bron[$key]['voorbeeld']) + ['email' => 'sophie@voorbeeld.nl', 'niveau' => 'Bronze', 'bedrag' => '€ 295,00', 'omschrijving' => 'Abonnement Groei', 'datum' => 'ma 1 dec', 'tijd' => '14:05'];
        $html = (string) (new PlatformMelding(MessageTemplate::where('key', $key)->sole(), $vars, 'https://mebranding.nl/app'))->toMail($this->user)->render();
        expect($html)->not->toMatch('/\{[a-z]+\}/')->and($html)->toContain('Team MEBRANDING');
    }
});

test('de seeder werkt standaardteksten bij, maar laat aanpassingen van het team staan', function () {
    MessageTemplate::where('key', 'c-feedback')->update(['subject' => 'Oud onderwerp', 'standaard_hash' => null]);
    MessageTemplate::where('key', 'c-goedgekeurd')->sole()->update(['subject' => 'Eigen tekst van het team']);

    $this->seed(MessageTemplateSeeder::class);

    expect(MessageTemplate::where('key', 'c-feedback')->value('subject'))->toBe('Feedback op je content voor {zaak}')
        ->and(MessageTemplate::where('key', 'c-goedgekeurd')->value('subject'))->toBe('Eigen tekst van het team');
});

test('dagoverzicht: ongelezen samenvattingssignalen in één mail voor het teamlid', function () {
    $this->seed(RolesSeeder::class);
    $team = User::factory()->create(['name' => 'Taipan Test']);
    $team->assignRole('Super Admin');
    $team->notifyNow(($this->melding)('t-zoeken-lang'), ['database']);
    $team->notifyNow(($this->melding)('t-nieuw'), ['database']);
    $this->user->notifyNow(($this->melding)('t-zoeken-lang'), ['database']); // geen teamlid

    Notification::fake();
    expect(app(Dagoverzicht::class)->stuur())->toBe(1);
    Notification::assertSentTo($team, PlatformMelding::class, fn ($m) => $m->template->key === 't-dagoverzicht' && $m->vars['aantal'] === '2' && count(json_decode($m->vars['regels'], true)) === 2);
});
