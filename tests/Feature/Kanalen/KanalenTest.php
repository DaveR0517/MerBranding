<?php

use App\Jobs\VerversEigenKanalen;
use App\Models\ActivityLog;
use App\Models\KanaalBericht;
use App\Models\KanaalGesprek;
use App\Models\KanaalMedia;
use App\Models\KanaalMeting;
use App\Models\KanaalReactie;
use App\Models\SocialAccount;
use App\Models\StudioDesign;
use App\Models\StudioExport;
use App\Models\User;
use App\Services\Kanalen\EigenKanalen;
use App\Services\Koppelingen\TikTokPlatform;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

const KANALEN_APP = 'http://app.mebranding.test';
const IG = 'https://graph.instagram.com/v23.0';
const TT = 'https://open.tiktokapis.com/v2';

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    Http::preventStrayRequests();
    $this->wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
    $this->baas = User::factory()->create();
    $this->baas->assignRole('Super Admin');

    // routes/portaal-webhooks.php wordt door routes/web.php geladen zodra dat is aangezet; in de test laden we hem zelf.
    if (! Route::has('webhooks.instagram')) {
        Route::domain(config('mebranding.domains.app'))->prefix(config('mebranding.app_prefix'))->middleware('web')->group(base_path('routes/portaal-webhooks.php'));
        app('router')->getRoutes()->refreshNameLookups();
    }

    $this->sleutels = function () {
        config([
            'services.instagram.client_id' => 'ig-app', 'services.instagram.client_secret' => 'ig-geheim', 'services.instagram.webhook_verify_token' => 'verifieer-mij',
            'services.tiktok.client_id' => 'tt-key', 'services.tiktok.client_secret' => 'tt-geheim', 'kanalen.plaatsen_wacht_seconden' => 0,
        ]);
    };
    $this->ig = function (array $extra = []) {
        return SocialAccount::create($extra + [
            'owner_type' => SocialAccount::EIGEN, 'owner_id' => 0, 'platform' => 'instagram', 'status' => 'gekoppeld', 'handle' => '@mebranding',
            'external_id' => '1789', 'access_token' => 'IG-TOKEN', 'expires_at' => now()->addDays(55), 'token_expires_at' => now()->addDays(55),
            'scopes' => config('kanalen.scopes.instagram'),
        ]);
    };
});

test('alleen met kanalen.beheren: Accountmanager en Super Admin wel, Contentcontrole en ondernemer niet', function () {
    $this->actingAs(($this->wie)('ruben'))->get(KANALEN_APP.'/team/kanalen')->assertForbidden();
    $this->actingAs(($this->wie)('sanne'))->get(KANALEN_APP.'/team/kanalen')->assertForbidden();
    $this->actingAs(($this->wie)('eva'))->get(KANALEN_APP.'/team/kanalen')->assertOk();
    foreach (['', '/posts', '/berichten'] as $scherm) {
        $this->actingAs($this->baas)->get(KANALEN_APP."/team/kanalen{$scherm}")->assertOk();
    }
    expect(($this->wie)('mirthe')->can('kanalen.beheren'))->toBeTrue();
});

test('zonder sleutels: niet gekoppeld, checklist met de exacte adressen en env-variabelen, koppelen geeft een melding', function () {
    $this->actingAs($this->baas)->get(KANALEN_APP.'/team/kanalen')->assertInertia(fn (AssertableInertia $p) => $p
        ->component('team/kanalen/overzicht')
        ->where('kanalen.0.platform', 'instagram')->where('kanalen.0.ingesteld', false)->where('kanalen.0.status', 'niet_gekoppeld')
        ->where('checklist.instagram.redirect', KANALEN_APP.'/team/kanalen/instagram/terug')
        ->where('checklist.instagram.webhook', KANALEN_APP.'/webhooks/instagram')
        ->where('checklist.tiktok.redirect', KANALEN_APP.'/team/kanalen/tiktok/terug')
        ->where('checklist.tiktok.env.0.naam', 'TIKTOK_CLIENT_KEY')
        ->where('checklist.instagram.env.2.naam', 'INSTAGRAM_WEBHOOK_VERIFY_TOKEN'));

    $this->actingAs($this->baas)->post(KANALEN_APP.'/team/kanalen/instagram/koppelen')->assertRedirect()->assertSessionHas('melding');
    Http::assertNothingSent();
});

test('Instagram koppelen: alle rechten gevraagd, code naar kort en lang token, webhooks aan, eerste keer ophalen', function () {
    ($this->sleutels)();
    $uit = $this->actingAs($this->baas)->withHeader('X-Inertia', 'true')->post(KANALEN_APP.'/team/kanalen/instagram/koppelen');
    $uit->assertStatus(409);
    $adres = $uit->headers->get('X-Inertia-Location');
    expect($adres)->toStartWith('https://www.instagram.com/oauth/authorize?')
        ->toContain('instagram_business_manage_messages')->toContain('instagram_business_content_publish')
        ->toContain(urlencode(KANALEN_APP.'/team/kanalen/instagram/terug'));
    parse_str((string) parse_url($adres, PHP_URL_QUERY), $q);
    $this->flushHeaders();

    Http::fake([
        'api.instagram.com/oauth/access_token' => Http::response(['data' => [['access_token' => 'KORT', 'user_id' => 1789, 'permissions' => implode(',', config('kanalen.scopes.instagram'))]]]),
        'graph.instagram.com/access_token*' => Http::response(['access_token' => 'LANG', 'token_type' => 'bearer', 'expires_in' => 5184000]),
        IG.'/me?*' => Http::response(['user_id' => '1789', 'username' => 'mebranding', 'name' => 'MEBRANDING', 'followers_count' => 1520, 'follows_count' => 80, 'media_count' => 2]),
        IG.'/me/subscribed_apps' => Http::response(['success' => true]),
        IG.'/me/media*' => Http::response(['data' => [['id' => 'm1', 'media_type' => 'VIDEO', 'media_product_type' => 'REELS', 'caption' => 'Reel', 'permalink' => 'https://www.instagram.com/reel/ABC/', 'timestamp' => now()->subDay()->toIso8601String(), 'like_count' => 12, 'comments_count' => 3]]]),
        IG.'/m1/insights*' => Http::response(['data' => [['name' => 'views', 'values' => [['value' => 900]]], ['name' => 'reach', 'values' => [['value' => 700]]], ['name' => 'saved', 'total_value' => ['value' => 9]]]]),
        IG.'/me/insights*' => Http::response(['data' => [['name' => 'reach', 'total_value' => ['value' => 400]], ['name' => 'views', 'total_value' => ['value' => 1100]]]]),
        IG.'/me/conversations*' => Http::response(['data' => []]),
    ]);

    $this->actingAs($this->baas)->get(KANALEN_APP."/team/kanalen/instagram/terug?code=CODE%23_&state={$q['state']}")
        ->assertRedirect(KANALEN_APP.'/team/kanalen')->assertSessionHas('melding', 'Instagram is gekoppeld.');

    $a = SocialAccount::eigen()->where('platform', 'instagram')->first();
    expect($a->status)->toBe('gekoppeld')->and($a->access_token)->toBe('LANG')->and($a->external_id)->toBe('1789')->and($a->handle)->toBe('@mebranding')
        ->and($a->heeftScope('instagram_business_content_publish'))->toBeTrue()->and($a->expires_at->diffInDays(now(), true))->toBeGreaterThan(59)
        ->and($a->getRawOriginal('access_token'))->not->toContain('LANG');
    expect(KanaalMedia::first()->cijfers)->toMatchArray(['views' => 900, 'reach' => 700, 'saved' => 9, 'likes' => 12, 'comments' => 3]);
    expect(KanaalMeting::where('metriek', 'volgers')->value('waarde'))->toBe(1520)
        ->and(KanaalMeting::where('metriek', 'weergaven')->whereDate('datum', today()->subDay())->value('waarde'))->toBe(1100);

    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.instagram.com/oauth/access_token' && $r['code'] === 'CODE' && $r['grant_type'] === 'authorization_code' && $r['client_secret'] === 'ig-geheim');
    Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), 'https://graph.instagram.com/access_token') && $r['grant_type'] === 'ig_exchange_token' && $r['access_token'] === 'KORT');
    Http::assertSent(fn (HttpRequest $r) => $r->url() === IG.'/me/subscribed_apps' && $r['subscribed_fields'] === 'messages,comments');
    // Accountcijfers met total_value en per dag.
    Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), IG.'/me/insights') && ($r['metric_type'] ?? null) === 'total_value' && $r['period'] === 'day');
});

test('terugkomst met een verkeerde state of een afgebroken inlog koppelt niets', function () {
    ($this->sleutels)();
    $this->actingAs($this->baas)->withSession(['kanalen.instagram' => 'goed'])->get(KANALEN_APP.'/team/kanalen/instagram/terug?code=x&state=fout')->assertSessionHas('melding', 'Koppelen is niet gelukt. Probeer het opnieuw.');
    $this->actingAs($this->baas)->withSession(['kanalen.tiktok' => 'goed'])->get(KANALEN_APP.'/team/kanalen/tiktok/terug?error=access_denied&state=goed')->assertSessionHas('melding');
    expect(SocialAccount::eigen()->count())->toBe(0);
    Http::assertNothingSent();
});

test('media met paginering: de volgende-link van Meta wordt gevolgd', function () {
    ($this->sleutels)();
    $a = ($this->ig)();
    Http::fake(function (HttpRequest $r) {
        if (str_contains($r->url(), 'after=PAGINA2')) {
            return Http::response(['data' => [['id' => 'm3', 'media_type' => 'IMAGE', 'media_product_type' => 'FEED', 'timestamp' => '2026-11-01T10:00:00+0000']]]);
        }

        return Http::response(['data' => [['id' => 'm1', 'media_type' => 'IMAGE'], ['id' => 'm2', 'media_type' => 'CAROUSEL_ALBUM']], 'paging' => ['next' => IG.'/me/media?after=PAGINA2&access_token=IG-TOKEN']]);
    });

    $r = app(EigenKanalen::class)->platform('instagram')->media($a, 3);
    expect($r->isGelukt())->toBeTrue()->and(collect($r->data['items'])->pluck('extern_id')->all())->toBe(['m1', 'm2', 'm3']);
    Http::assertSentCount(2);
});

test('tokens verlengen: Instagram vanaf dag 50, TikTok als het toegangstoken bijna verloopt', function () {
    ($this->sleutels)();
    $ig = ($this->ig)(['expires_at' => now()->addDays(8)]);
    $tt = SocialAccount::create(['owner_type' => SocialAccount::EIGEN, 'owner_id' => 0, 'platform' => 'tiktok', 'status' => 'gekoppeld', 'external_id' => 'open-1',
        'access_token' => 'TT-OUD', 'refresh_token' => 'TT-VERNIEUW', 'token_expires_at' => now()->addHour(), 'expires_at' => now()->addDays(300)]);
    Http::fake([
        'graph.instagram.com/refresh_access_token*' => Http::response(['access_token' => 'IG-NIEUW', 'expires_in' => 5184000]),
        TT.'/oauth/token/' => Http::response(['access_token' => 'TT-NIEUW', 'expires_in' => 86400, 'refresh_token' => 'TT-VERNIEUW-2', 'refresh_expires_in' => 31536000, 'open_id' => 'open-1', 'scope' => 'user.info.basic,video.list']),
    ]);
    $k = app(EigenKanalen::class);

    expect($k->verlengIndienNodig($ig)->isGelukt())->toBeTrue()->and($k->verlengIndienNodig($tt)->isGelukt())->toBeTrue();
    expect($ig->fresh()->access_token)->toBe('IG-NIEUW')->and($ig->fresh()->expires_at->gt(now()->addDays(59)))->toBeTrue();
    expect($tt->fresh()->access_token)->toBe('TT-NIEUW')->and($tt->fresh()->refresh_token)->toBe('TT-VERNIEUW-2')->and($tt->fresh()->token_expires_at->gt(now()->addHours(23)))->toBeTrue();
    Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), 'https://graph.instagram.com/refresh_access_token') && $r['grant_type'] === 'ig_refresh_token');
    Http::assertSent(fn (HttpRequest $r) => $r->url() === TT.'/oauth/token/' && $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'TT-VERNIEUW');

});

test('een vers Instagram-token wordt niet verlengd', function () {
    ($this->sleutels)();
    expect(app(EigenKanalen::class)->verlengIndienNodig(($this->ig)(['expires_at' => now()->addDays(59)]))->isGelukt())->toBeTrue();
    Http::assertNothingSent();
});

test('gesprekken ophalen en antwoorden binnen het 24-uursvenster; daarbuiten gaat er niets naar Meta', function () {
    ($this->sleutels)();
    $a = ($this->ig)();
    Http::fake([
        IG.'/me/conversations*' => Http::response(['data' => [['id' => 'conv-1', 'updated_time' => now()->toIso8601String(), 'participants' => ['data' => [['id' => '1789', 'username' => 'mebranding'], ['id' => 'igsid-9', 'username' => 'bakkerijdejong']]]]]]),
        IG.'/conv-1*' => Http::response(['id' => 'conv-1', 'messages' => ['data' => [
            ['id' => 'mid-2', 'created_time' => now()->subHour()->toIso8601String(), 'from' => ['id' => '1789'], 'message' => 'Graag gedaan!'],
            ['id' => 'mid-1', 'created_time' => now()->subHours(2)->toIso8601String(), 'from' => ['id' => 'igsid-9'], 'message' => 'Wat kost een samenwerking?'],
        ]]]),
        IG.'/me/messages' => Http::response(['recipient_id' => 'igsid-9', 'message_id' => 'mid-3']),
    ]);
    $k = app(EigenKanalen::class);
    (fn () => $this->instagramGesprekken($a, $this->platform('instagram')))->call($k);

    $g = KanaalGesprek::first();
    expect($g->deelnemer_naam)->toBe('bakkerijdejong')->and($g->berichten()->pluck('richting')->all())->toBe(['in', 'uit'])->and($g->antwoordenTot())->not->toBeNull();

    $this->actingAs($this->baas)->post(KANALEN_APP."/team/kanalen/berichten/{$g->id}", ['tekst' => 'Ik bel je morgen.'])->assertSessionHasNoErrors();
    Http::assertSent(fn (HttpRequest $r) => $r->url() === IG.'/me/messages' && $r['recipient']['id'] === 'igsid-9' && $r['message']['text'] === 'Ik bel je morgen.' && $r->hasHeader('Authorization', 'Bearer IG-TOKEN'));
    expect(KanaalBericht::where('extern_id', 'mid-3')->first())->richting->toBe('uit')->user_id->toBe($this->baas->id);

    $g->update(['laatste_inkomend_op' => now()->subHours(25)]);
    $this->actingAs($this->baas)->post(KANALEN_APP."/team/kanalen/berichten/{$g->id}", ['tekst' => 'Te laat'])->assertSessionHasErrors('tekst');
    Http::assertSentCount(3);

    // Openen is lezen.
    $g->update(['ongelezen' => 2]);
    $this->actingAs($this->baas)->get(KANALEN_APP."/team/kanalen/berichten?gesprek={$g->id}")->assertInertia(fn (AssertableInertia $p) => $p->where('gesprek.naam', 'bakkerijdejong')->where('gesprek.antwoorden_tot', null)
        ->where('gesprek.berichten.0.tekst', 'Wat kost een samenwerking?')->where('gesprek.berichten.2.tekst', 'Ik bel je morgen.')->where('gesprekken.0.laatste', 'Ik bel je morgen.'));
    expect($g->fresh()->ongelezen)->toBe(0);
});

test('webhook Instagram: verificatie met verify token, handtekening verplicht, bericht en reactie bewaard met melding', function () {
    ($this->sleutels)();
    $a = ($this->ig)();

    $this->get(KANALEN_APP.'/webhooks/instagram?hub.mode=subscribe&hub.verify_token=verifieer-mij&hub.challenge=12345')->assertOk()->assertSee('12345');
    $this->get(KANALEN_APP.'/webhooks/instagram?hub.mode=subscribe&hub.verify_token=fout&hub.challenge=12345')->assertForbidden();

    $payload = json_encode(['object' => 'instagram', 'entry' => [[
        'id' => '1789', 'time' => time(),
        'messaging' => [['sender' => ['id' => 'igsid-5'], 'recipient' => ['id' => '1789'], 'timestamp' => now()->getTimestampMs(), 'message' => ['mid' => 'mid-w1', 'text' => 'Hoi, doen jullie ook Leeuwarden?']]],
        'changes' => [['field' => 'comments', 'value' => ['id' => 'c-1', 'text' => 'Mooi!', 'from' => ['id' => 'u-7', 'username' => 'lunchroombloesem'], 'media' => ['id' => 'm-9', 'media_product_type' => 'REELS']]]],
    ]]]);
    $verstuur = fn (string $sig) => $this->call('POST', KANALEN_APP.'/webhooks/instagram', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $sig], $payload);

    $verstuur('sha256='.hash_hmac('sha256', $payload, 'verkeerd'))->assertForbidden();
    expect(KanaalBericht::count())->toBe(0);

    $verstuur('sha256='.hash_hmac('sha256', $payload, 'ig-geheim'))->assertOk();
    $verstuur('sha256='.hash_hmac('sha256', $payload, 'ig-geheim'))->assertOk(); // Meta stuurt soms dubbel
    expect(KanaalBericht::count())->toBe(1)->and(KanaalGesprek::first()->ongelezen)->toBe(1)->and(KanaalReactie::first()->gebruikersnaam)->toBe('lunchroombloesem');
    expect(($this->wie)('eva')->notifications()->count())->toBe(2)->and(($this->wie)('ruben')->notifications()->count())->toBe(0);
    Http::assertNothingSent();
});

test('reacties ophalen en beantwoorden op Instagram', function () {
    ($this->sleutels)();
    $a = ($this->ig)();
    $m = KanaalMedia::create(['social_account_id' => $a->id, 'extern_id' => 'm-1', 'soort' => 'IMAGE', 'product' => 'FEED', 'geplaatst_op' => now()]);
    Http::fake([
        IG.'/m-1/comments*' => Http::response(['data' => [['id' => 'c-1', 'text' => 'Top', 'username' => 'sophie', 'timestamp' => now()->toIso8601String(), 'like_count' => 2, 'replies' => ['data' => [['id' => 'c-2', 'text' => 'Dank je!', 'username' => 'mebranding']]]]]]),
        IG.'/c-1/replies' => Http::response(['id' => 'c-3']),
    ]);

    $this->actingAs($this->baas)->post(KANALEN_APP."/team/kanalen/posts/{$m->id}/reacties")->assertSessionHas('melding', 'Reacties zijn bijgewerkt.');
    expect(KanaalReactie::count())->toBe(2)->and(KanaalReactie::where('extern_id', 'c-2')->first())->eigen->toBeTrue()->ouder_extern_id->toBe('c-1');

    $this->actingAs($this->baas)->post(KANALEN_APP.'/team/kanalen/reacties/'.KanaalReactie::where('extern_id', 'c-1')->value('id').'/antwoord', ['tekst' => 'Graag gedaan'])->assertSessionHasNoErrors();
    Http::assertSent(fn (HttpRequest $r) => $r->url() === IG.'/c-1/replies' && $r['message'] === 'Graag gedaan');
    expect(KanaalReactie::where('extern_id', 'c-3')->first()->user_id)->toBe($this->baas->id);

    $this->actingAs($this->baas)->get(KANALEN_APP."/team/kanalen/posts?post={$m->id}")->assertInertia(fn (AssertableInertia $p) => $p
        ->component('team/kanalen/posts')->where('detail.id', $m->id)->where('detail.mag_antwoorden', true)->has('detail.reacties', 3));
});

test('TikTok: koppelen, profiel en video-lijst met cursor; geen berichten-API', function () {
    ($this->sleutels)();
    Http::fake(function (HttpRequest $r) {
        return match (true) {
            $r->url() === TT.'/oauth/token/' => Http::response(['access_token' => 'TT-A', 'expires_in' => 86400, 'refresh_token' => 'TT-R', 'refresh_expires_in' => 31536000, 'open_id' => 'open-9', 'scope' => 'user.info.basic,user.info.profile,user.info.stats,video.list']),
            str_starts_with($r->url(), TT.'/user/info/') => Http::response(['data' => ['user' => ['open_id' => 'open-9', 'username' => 'mebranding', 'display_name' => 'MEBRANDING', 'follower_count' => 3400, 'likes_count' => 51000, 'video_count' => 3]], 'error' => ['code' => 'ok', 'message' => '']]),
            str_starts_with($r->url(), TT.'/video/list/') && ($r['cursor'] ?? null) === null => Http::response(['data' => ['videos' => [['id' => 'v1', 'title' => 'Eerste', 'create_time' => 1763800000, 'view_count' => 1200, 'like_count' => 80, 'comment_count' => 4, 'share_count' => 6, 'share_url' => 'https://www.tiktok.com/@mebranding/video/v1']], 'cursor' => 1763700000, 'has_more' => true], 'error' => ['code' => 'ok']]),
            str_starts_with($r->url(), TT.'/video/list/') => Http::response(['data' => ['videos' => [['id' => 'v2', 'title' => 'Tweede', 'create_time' => 1763600000, 'view_count' => 300]], 'cursor' => 0, 'has_more' => false], 'error' => ['code' => 'ok']]),
        };
    });

    $r = app(EigenKanalen::class)->koppel('tiktok', 'TT-CODE', KANALEN_APP.'/team/kanalen/tiktok/terug', $this->baas);
    expect($r->isGelukt())->toBeTrue();
    $a = SocialAccount::eigen()->where('platform', 'tiktok')->first();
    expect($a->handle)->toBe('@mebranding')->and($a->refresh_token)->toBe('TT-R')->and($a->token_expires_at->lt(now()->addDays(2)))->toBeTrue()->and($a->expires_at->gt(now()->addDays(300)))->toBeTrue();
    expect(KanaalMedia::where('social_account_id', $a->id)->pluck('extern_id')->all())->toEqualCanonicalizing(['v1', 'v2'])
        ->and(KanaalMedia::where('extern_id', 'v1')->first()->cijfers)->toMatchArray(['views' => 1200, 'likes' => 80, 'shares' => 6]);
    expect(KanaalMeting::where('metriek', 'video_weergaven_totaal')->value('waarde'))->toBe(1500)->and(KanaalMeting::where('metriek', 'volgers')->value('waarde'))->toBe(3400);

    Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), TT.'/video/list/?fields=') && str_contains($r->url(), 'view_count') && $r['max_count'] === 20 && ! isset($r['cursor']));
    Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), TT.'/video/list/') && ($r['cursor'] ?? null) === 1763700000);
    Http::assertSent(fn (HttpRequest $r) => $r->url() === TT.'/oauth/token/' && $r['client_key'] === 'tt-key' && $r['code'] === 'TT-CODE');

    // Creatorkoppelingen profiteren mee: cijfers van een post via het adres.
    $cijfers = app(TikTokPlatform::class)->haalCijfersOp($a, 'https://www.tiktok.com/@mebranding/video/v1');
    expect($cijfers->isGelukt())->toBeTrue()->and($cijfers->data['views'])->toBe(1200);

    // Het overzicht zegt eerlijk dat er bij TikTok geen berichten zijn.
    $this->actingAs($this->baas)->get(KANALEN_APP.'/team/kanalen')->assertInertia(fn (AssertableInertia $p) => $p->where('kanalen.1.status', 'gekoppeld')->where('kanalen.1.berichten', false)->where('kanalen.1.volgers', 3400));
});

test('webhook TikTok: handtekening met tijdstempel, ingetrokken toegang zet de koppeling uit', function () {
    ($this->sleutels)();
    $a = SocialAccount::create(['owner_type' => SocialAccount::EIGEN, 'owner_id' => 0, 'platform' => 'tiktok', 'status' => 'gekoppeld', 'external_id' => 'open-9', 'access_token' => 'x']);
    $body = json_encode(['client_key' => 'tt-key', 'event' => 'authorization.removed', 'create_time' => time(), 'user_openid' => 'open-9', 'content' => '{"reason":1}']);
    $stuur = fn (string $kop) => $this->call('POST', KANALEN_APP.'/webhooks/tiktok', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_TIKTOK_SIGNATURE' => $kop], $body);
    $t = time();

    $stuur("t={$t},s=".hash_hmac('sha256', "{$t}.{$body}", 'fout'))->assertForbidden();
    $oud = $t - 600;
    $stuur("t={$oud},s=".hash_hmac('sha256', "{$oud}.{$body}", 'tt-geheim'))->assertForbidden();
    $stuur("t={$t},s=".hash_hmac('sha256', "{$t}.{$body}", 'tt-geheim'))->assertOk();
    expect($a->fresh()->status)->toBe('niet_gekoppeld')->and($a->fresh()->access_token)->toBeNull();
});

test('ondertekend verzoek van Meta: ontkoppelen en gegevens verwijderen', function () {
    ($this->sleutels)();
    $a = ($this->ig)();
    KanaalMedia::create(['social_account_id' => $a->id, 'extern_id' => 'm-1']);
    $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $data = $b64(json_encode(['algorithm' => 'HMAC-SHA256', 'user_id' => '1789', 'issued_at' => time()]));
    $signed = $b64(hash_hmac('sha256', $data, 'ig-geheim', true)).'.'.$data;

    $this->post(KANALEN_APP.'/webhooks/instagram/verwijderen', ['signed_request' => 'x.'.$data])->assertForbidden();
    $this->post(KANALEN_APP.'/webhooks/instagram/verwijderen', ['signed_request' => $signed])->assertOk()->assertJsonStructure(['url', 'confirmation_code']);
    expect($a->fresh()->status)->toBe('niet_gekoppeld')->and(KanaalMedia::count())->toBe(0);
});

test('plaatsen vanuit MeStudio: knop alleen met recht en koppeling, tijdelijke publieke link, container, status, publiceren', function () {
    ($this->sleutels)();
    Storage::fake('local');
    $ontwerp = StudioDesign::create(['user_id' => $this->baas->id, 'name' => 'Kerstpost', 'format' => 'instagram_post', 'width' => 1080, 'height' => 1080, 'pages' => [['lagen' => []]], 'status' => 'klaar', 'caption' => 'Fijne feestdagen #mebranding']);
    Storage::disk('local')->put('studio/exports/1/kerst.jpg', 'jpg-inhoud');
    $export = StudioExport::create(['design_id' => $ontwerp->id, 'user_id' => $this->baas->id, 'kind' => 'jpg', 'page' => 1, 'path' => 'studio/exports/1/kerst.jpg', 'mime' => 'image/jpeg', 'size' => 10, 'status' => 'klaar']);

    // Zonder koppeling: geen knop, en de route weigert.
    $this->actingAs(($this->wie)('eva'))->get(KANALEN_APP.'/team/studio/feedplanner')->assertInertia(fn (AssertableInertia $p) => $p->where('instagram_plaatsen', false));
    $this->actingAs($this->baas)->post(KANALEN_APP."/team/kanalen/plaatsen/{$ontwerp->id}")->assertSessionHas('melding');

    ($this->ig)();
    $this->actingAs(($this->wie)('eva'))->get(KANALEN_APP.'/team/studio/feedplanner')->assertInertia(fn (AssertableInertia $p) => $p->where('instagram_plaatsen', true));
    $this->actingAs(($this->wie)('ruben'))->post(KANALEN_APP."/team/kanalen/plaatsen/{$ontwerp->id}")->assertForbidden();

    $statussen = ['IN_PROGRESS', 'FINISHED'];
    Http::fake([
        IG.'/me/media' => Http::response(['id' => 'container-1']),
        IG.'/container-1*' => function () use (&$statussen) {
            return Http::response(['status_code' => array_shift($statussen)]);
        },
        IG.'/me/media_publish' => Http::response(['id' => 'media-77']),
        IG.'/media-77*' => Http::response(['permalink' => 'https://www.instagram.com/p/KERST/']),
    ]);

    $this->actingAs(($this->wie)('eva'))->post(KANALEN_APP."/team/kanalen/plaatsen/{$ontwerp->id}")->assertSessionHas('melding');

    expect($ontwerp->fresh())->status->toBe('geplaatst')->post_url->toBe('https://www.instagram.com/p/KERST/');
    expect(ActivityLog::where('event', 'instagram_geplaatst')->where('subject_id', $ontwerp->id)->exists())->toBeTrue();
    $bestandUrl = null;
    Http::assertSent(function (HttpRequest $r) use (&$bestandUrl) {
        if ($r->url() === IG.'/me/media' && $r['caption'] === 'Fijne feestdagen #mebranding') {
            $bestandUrl = $r['image_url'];

            return true;
        }

        return false;
    });
    Http::assertSent(fn (HttpRequest $r) => $r->url() === IG.'/me/media_publish' && $r['creation_id'] === 'container-1');

    // De tijdelijke link werkt zonder inloggen, maar niet zonder geldige handtekening of na een uur.
    auth()->logout();
    expect($bestandUrl)->toContain('/kanalen/bestand/'.$export->id)->toContain('signature=');
    $this->get($bestandUrl)->assertOk();
    $this->get(KANALEN_APP.'/kanalen/bestand/'.$export->id)->assertForbidden();
    $this->travel(61)->minutes();
    $this->get($bestandUrl)->assertForbidden();
});

test('PNG of een formaat dat niet bij Instagram past wordt niet geplaatst', function () {
    ($this->sleutels)();
    ($this->ig)();
    $ontwerp = StudioDesign::create(['user_id' => $this->baas->id, 'name' => 'LinkedIn', 'format' => 'linkedin_post', 'width' => 1200, 'height' => 1200, 'pages' => [['lagen' => []]], 'status' => 'klaar']);
    $this->actingAs($this->baas)->post(KANALEN_APP."/team/kanalen/plaatsen/{$ontwerp->id}")->assertSessionHasErrors('plaatsen');
    $ontwerp->update(['format' => 'instagram_post']);
    StudioExport::create(['design_id' => $ontwerp->id, 'user_id' => $this->baas->id, 'kind' => 'png', 'page' => 1, 'path' => 'x.png', 'mime' => 'image/png', 'size' => 1, 'status' => 'klaar']);
    $this->actingAs($this->baas)->post(KANALEN_APP."/team/kanalen/plaatsen/{$ontwerp->id}")->assertSessionHasErrors(['plaatsen' => 'Exporteer het ontwerp als JPG of MP4. Instagram neemt geen PNG.']);
    Http::assertNothingSent();
});

test('uurlijkse job: ververst gekoppelde kanalen; een ingetrokken token zet de status op fout en meldt het team één keer', function () {
    ($this->sleutels)();
    $a = ($this->ig)();
    Http::fake([IG.'/me?*' => Http::response(['error' => ['message' => 'Error validating access token', 'type' => 'OAuthException', 'code' => 190]], 400)]);

    (new VerversEigenKanalen)->handle(app(EigenKanalen::class));
    expect($a->fresh()->status)->toBe('fout')->and($a->fresh()->last_error)->toContain('Error validating access token');
    expect(($this->wie)('eva')->notifications()->count())->toBe(1);

    // Status fout: de job slaat het account over (opnieuw koppelen nodig), dus geen tweede melding.
    (new VerversEigenKanalen)->handle(app(EigenKanalen::class));
    expect(($this->wie)('eva')->notifications()->count())->toBe(1);
});

test('de job staat elk uur in de planning', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('eigen-kanalen-verversen');
});

test('eigen kanalen staan niet tussen de klantkoppelingen en breken de monitor niet', function () {
    ($this->ig)();
    expect(SocialAccount::count())->toBe(SocialAccount::withoutGlobalScope('klanten')->count() - 1);
    $this->actingAs($this->baas)->get(KANALEN_APP.'/team/content/koppelingen')->assertOk();
});
