<?php

use App\Models\MessageTemplate;
use App\Models\PushAbonnement;
use App\Models\User;
use App\Notifications\Kanalen\WebpushKanaal;
use App\Notifications\PlatformMelding;
use App\Services\Webpush;
use Database\Seeders\DemoSeeder;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/* Webapp (installeren op het beginscherm) en pushmeldingen. */
uses(RefreshDatabase::class);

const APP_WA = 'http://app.mebranding.test';

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DemoSeeder::class);
    $this->sophie = User::where('email', 'sophie@demo.mebranding.nl')->sole();
    $this->abonnement = fn (User $u, string $eind = 'abc') => [
        'endpoint' => "https://fcm.googleapis.com/fcm/send/{$eind}", 'keys' => ['p256dh' => 'BPubliek', 'auth' => 'geheim'], 'contentEncoding' => 'aes128gcm',
    ];
});

test('manifest en service worker zijn er zonder inloggen, met het portaal als bereik', function () {
    $this->get(APP_WA.'/manifest.webmanifest')->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('name', 'MEBRANDING')->assertJsonPath('display', 'standalone')
        ->assertJsonPath('start_url', '/')->assertJsonPath('scope', '/')
        ->assertJsonCount(3, 'icons');
    expect(file_exists(public_path('brand/app-icoon-512.png')))->toBeTrue();

    $this->get(APP_WA.'/sw.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8')->assertSee("addEventListener('push'", false);
});

test('de pushsleutel maakt de server zelf aan, versleuteld, en hij blijft gelijk', function () {
    $this->get(APP_WA.'/push/sleutel')->assertRedirect();
    $sleutel = $this->actingAs($this->sophie)->getJson(APP_WA.'/push/sleutel')->assertOk()->json('key');

    expect(strlen($sleutel))->toBe(87)
        ->and(Storage::disk('local')->get('webpush/vapid.json'))->not->toContain($sleutel) // versleuteld opgeslagen
        ->and(app(Webpush::class)->publiekeSleutel())->toBe($sleutel);
});

test('een apparaat aanmelden en afmelden, alleen je eigen', function () {
    $this->actingAs($this->sophie)->postJson(APP_WA.'/push/abonneren', ($this->abonnement)($this->sophie))->assertOk();
    $this->actingAs($this->sophie)->postJson(APP_WA.'/push/abonneren', ($this->abonnement)($this->sophie))->assertOk(); // zelfde apparaat: geen dubbele
    expect(PushAbonnement::where('user_id', $this->sophie->id)->count())->toBe(1);

    $this->actingAs($this->sophie)->postJson(APP_WA.'/push/abonneren', ['endpoint' => 'http://onveilig.test/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertUnprocessable();

    $ander = User::where('email', 'marit@demo.mebranding.nl')->sole();
    $this->actingAs($ander)->postJson(APP_WA.'/push/opzeggen', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])->assertOk();
    expect(PushAbonnement::count())->toBe(1);
    $this->actingAs($this->sophie)->postJson(APP_WA.'/push/opzeggen', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])->assertOk();
    expect(PushAbonnement::count())->toBe(0);
});

test('een melding gaat als push mee als het sjabloon push aan heeft en het apparaat is aangemeld, met de korte pushtekst', function () {
    $this->seed(MessageTemplateSeeder::class);
    $sjabloon = MessageTemplate::where('key', 'c-uitnodiging')->sole();
    expect($sjabloon->wants('push'))->toBeTrue()->and($sjabloon->push)->toContain('Ja of nee?');

    $melding = new PlatformMelding($sjabloon, ['zaak' => 'Cheung Kwong Franeker', 'plaats' => 'Franeker', 'bedrag' => 'gratis bezoek'], 'https://mebranding.nl/app/creator');
    expect($melding->via($this->sophie))->not->toContain(WebpushKanaal::class);

    PushAbonnement::create(['user_id' => $this->sophie->id, 'endpoint' => 'https://push.test/1', 'public_key' => 'a', 'auth_token' => 'b']);
    expect($melding->via($this->sophie->fresh()))->toContain(WebpushKanaal::class)
        ->and($melding->toWebpush($this->sophie)['body'])->toBe('Nieuwe samenwerking: Cheung Kwong Franeker in Franeker, gratis bezoek. Ja of nee?');

    // Het kanaal stuurt via de dienst naar de apparaten van deze gebruiker.
    $verstuurd = [];
    app()->instance(Webpush::class, new class($verstuurd) extends Webpush
    {
        public function __construct(public array &$log) {}

        public function stuur(User $user, string $titel, string $tekst, string $url): int
        {
            $this->log[] = [$user->id, $titel, $tekst, $url];

            return 1;
        }
    });
    $this->sophie->fresh()->notifyNow($melding);
    expect($verstuurd)->toHaveCount(1)->and($verstuurd[0][3])->toBe('https://mebranding.nl/app/creator');
});
