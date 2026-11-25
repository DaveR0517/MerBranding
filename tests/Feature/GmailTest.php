<?php

use App\Models\User;
use App\Services\Gmail\Postvakken;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/* Gmail van MEBRANDING in het portaal: wie ziet welk postvak (besluit Dave, 27 nov) en lezen, beantwoorden, archiveren. */
uses(RefreshDatabase::class);

const APP_GM = 'http://app.mebranding.test';

beforeEach(function () {
    config(['mebranding.team_2fa_verplicht' => false]);
    $this->seed(DemoSeeder::class);
    $sleutel = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($sleutel, $pem);
    config([
        'gmail.service_account' => base64_encode(json_encode(['client_email' => 'portaal@mebranding.iam.gserviceaccount.com', 'private_key' => $pem])),
        'gmail.postvakken' => ['davey@mebranding.nl' => 'dave@mebranding.nl', 'taipan@mebranding.nl' => null, 'eline@mebranding.nl' => null, 'support@mebranding.nl' => null],
    ]);
    $maak = function (string $email, string $naam, string $rol) {
        $u = User::factory()->create(['email' => $email, 'name' => $naam]);
        $u->assignRole($rol);

        return $u;
    };
    $this->dave = $maak('dave@mebranding.nl', 'Dave', 'Super Admin');
    $this->taipan = $maak('taipan@mebranding.nl', 'Tai Pan', 'Super Admin');
    $this->eline = $maak('eline@mebranding.nl', 'Eline', 'Contentcontrole');
});

test('wie ziet welk postvak: eigen altijd, support voor de eigenaren, persoonlijk van een ander pas na akkoord', function () {
    $p = app(Postvakken::class);
    $zicht = fn (User $u) => collect($p->voor($u))->mapWithKeys(fn ($x) => [$x['postvak'] => $x['zichtbaar']])->all();

    expect($zicht($this->dave))->toBe(['davey@mebranding.nl' => true, 'taipan@mebranding.nl' => false, 'eline@mebranding.nl' => false, 'support@mebranding.nl' => true])
        ->and($zicht($this->eline))->toBe(['eline@mebranding.nl' => true]); // Eline ziet alleen haar eigen postvak, en de rest niet eens genoemd

    // Alleen Eline zelf kan akkoord geven voor haar postvak.
    $this->actingAs($this->dave)->post(APP_GM.'/team/kanalen/mail/eline@mebranding.nl/akkoord', ['akkoord' => true])->assertForbidden();
    $this->actingAs($this->eline)->post(APP_GM.'/team/kanalen/mail/eline@mebranding.nl/akkoord', ['akkoord' => true])->assertRedirect();
    expect($zicht($this->dave)['eline@mebranding.nl'])->toBeTrue()->and($zicht($this->taipan)['eline@mebranding.nl'])->toBeTrue();

    // Intrekken: weer alleen voor Eline.
    $this->actingAs($this->eline)->post(APP_GM.'/team/kanalen/mail/eline@mebranding.nl/akkoord', ['akkoord' => false])->assertRedirect();
    expect($zicht($this->dave)['eline@mebranding.nl'])->toBeFalse();

    // Menu: Mail voor wie een postvak heeft, niet voor een creator.
    $this->actingAs($this->eline)->get(APP_GM.'/team/kanalen/mail')->assertOk()->assertInertia(fn ($pg) => $pg->where('auth.permissions', fn ($r) => collect($r)->contains('mail.postvak')));
    $this->actingAs(User::where('email', 'eva@demo.mebranding.nl')->sole())->get(APP_GM.'/team/kanalen/mail')->assertForbidden();
});

test('lezen, beantwoorden en archiveren via Gmail, namens het juiste postvak; geen toegang tot een postvak zonder akkoord', function () {
    $verzonden = [];
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        'gmail.googleapis.com/gmail/v1/users/me/threads?*' => Http::response(['threads' => [['id' => 't1']], 'nextPageToken' => null]),
        'gmail.googleapis.com/gmail/v1/users/me/threads/t1/modify' => Http::response([]),
        'gmail.googleapis.com/gmail/v1/users/me/threads/t1*' => Http::response(['id' => 't1', 'messages' => [[
            'id' => 'm1', 'internalDate' => '1764000000000', 'labelIds' => ['INBOX', 'UNREAD'], 'snippet' => 'Hoi, wij willen graag',
            'payload' => ['mimeType' => 'multipart/alternative', 'headers' => [
                ['name' => 'From', 'value' => 'Restaurant Het Dok <info@hetdok.test>'], ['name' => 'To', 'value' => 'support@mebranding.nl'],
                ['name' => 'Subject', 'value' => 'Samenwerking'], ['name' => 'Message-ID', 'value' => '<abc@hetdok.test>'],
            ], 'parts' => [
                ['mimeType' => 'text/plain', 'body' => ['data' => rtrim(strtr(base64_encode('Hoi, wij willen graag samenwerken.'), '+/', '-_'), '=')]],
                ['mimeType' => 'text/html', 'body' => ['data' => rtrim(strtr(base64_encode('<p>Hoi, wij willen graag <b>samenwerken</b>.</p>'), '+/', '-_'), '=')]],
            ]],
        ]]]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send' => function (Request $r) use (&$verzonden) {
            $verzonden[] = $r->data();

            return Http::response(['id' => 'm2']);
        },
    ]);

    $this->actingAs($this->dave)->get(APP_GM.'/team/kanalen/mail?postvak=support@mebranding.nl&gesprek=t1')->assertOk()
        ->assertInertia(fn ($pg) => $pg->component('team/kanalen/mail')
            ->where('inbox.gesprekken.0.onderwerp', 'Samenwerking')->where('inbox.gesprekken.0.ongelezen', true)
            ->where('inbox.gesprekken.0.van.email', 'info@hetdok.test')
            ->where('gesprek.berichten.0.html', '<p>Hoi, wij willen graag <b>samenwerken</b>.</p>')
            ->where('fout', null));

    // Het toegangstoken is aangevraagd namens support@ (JWT met sub = postvak).
    Http::assertSent(function (Request $r) {
        if (! str_contains($r->url(), 'oauth2.googleapis.com/token')) {
            return false;
        }
        $claims = json_decode(base64_decode(strtr(explode('.', $r->data()['assertion'])[1], '-_', '+/')), true);

        return $claims['sub'] === 'support@mebranding.nl' && str_contains($claims['scope'], 'gmail.modify');
    });

    $this->actingAs($this->dave)->post(APP_GM.'/team/kanalen/mail/support@mebranding.nl/t1/antwoord', ['tekst' => 'Leuk! We bellen je morgen.'])->assertSessionHasNoErrors();
    $mime = base64_decode(strtr($verzonden[0]['raw'], '-_', '+/'));
    expect($verzonden[0]['threadId'])->toBe('t1')
        ->and($mime)->toContain('From: support@mebranding.nl')->toContain('To: Restaurant Het Dok <info@hetdok.test>')
        ->toContain('In-Reply-To: <abc@hetdok.test>')->toContain(base64_encode('Re: Samenwerking'));

    $this->actingAs($this->dave)->post(APP_GM.'/team/kanalen/mail/support@mebranding.nl/t1/archiveren')->assertRedirect();
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/threads/t1/modify') && ($r->data()['removeLabelIds'] ?? []) === ['INBOX']);

    // Zonder akkoord van Eline: geen toegang tot haar postvak.
    $this->actingAs($this->dave)->post(APP_GM.'/team/kanalen/mail/eline@mebranding.nl/t1/antwoord', ['tekst' => 'x'])->assertForbidden();
});

test('mailer gmail: portaalmail gaat via de Gmail API vanuit support@, Bcc blijft bezorgd', function () {
    config(['mail.default' => 'gmail', 'mail.from.address' => 'support@mebranding.nl']);
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        'gmail.googleapis.com/*' => Http::response(['id' => 'm1']),
    ]);

    Mail::raw('Hallo', fn ($m) => $m->to('klant@voorbeeld.nl')->bcc('stil@voorbeeld.nl')->subject('Test'));

    Http::assertSent(function (Request $r) {
        if (! str_ends_with($r->url(), '/messages/send')) {
            return false;
        }
        $ruw = base64_decode(strtr($r['raw'], '-_', '+/'));

        return str_contains($ruw, 'To: klant@voorbeeld.nl') && str_starts_with($ruw, 'Bcc: stil@voorbeeld.nl') && str_contains($ruw, 'support@mebranding.nl');
    });
    // Token aangevraagd namens support@ (sub in de JWT).
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'oauth2') && str_contains(base64_decode(strtr(explode('.', $r['assertion'])[1], '-_', '+/')), '"sub":"support@mebranding.nl"'));
});
