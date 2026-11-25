<?php

use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Creator;
use App\Models\Invitation;
use App\Models\MessageTemplate;
use App\Models\StatSnapshot;
use App\Models\Status;
use App\Models\User;
use App\Notifications\PlatformMelding;
use App\Services\Cijfers;
use App\Services\Herinneringen;
use App\Services\Samenwerking;
use App\Services\Uitnodiging;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/*
 * Triggers van de keten en het account: elk sjabloon gaat op het juiste moment naar de juiste ontvanger,
 * met een waarde voor elke {placeholder} (onderwerp, push, in-app en mail) en een link naar de juiste pagina.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->startOfWeek()->setTime(10, 0)); // maandag 10:00
    Notification::fake();
    $this->seed(DemoSeeder::class);
    $this->keten = app(Samenwerking::class);
    $this->h = app(Herinneringen::class);
    $this->bloesem = Company::where('name', 'Lunchroom Bloesem')->first();
    $this->sanne = User::where('email', 'sanne@demo.mebranding.nl')->first();
    $this->eva = User::where('email', 'eva@demo.mebranding.nl')->first();
    $this->ruben = User::where('email', 'ruben@demo.mebranding.nl')->first();
    $this->marit = Creator::whereHas('user', fn ($q) => $q->where('email', 'marit@demo.mebranding.nl'))->first();
    $this->sophie = Creator::whereHas('user', fn ($q) => $q->where('email', 'sophie@demo.mebranding.nl'))->first();

    // Een lid van het bedrijf (geen eigenaar) en een Super Admin, om ontvangers te onderscheiden.
    $this->lid = User::factory()->create(['name' => 'Lars Lid']);
    $this->bloesem->users()->attach($this->lid->id, ['role' => 'lid']);
    $this->admin = User::factory()->create(['name' => 'Dave Admin']);
    $this->admin->assignRole('Super Admin');
});

function ketenSamenwerking(Company $bedrijf, ?Creator $creator, Status $status, array $velden = []): Collaboration
{
    return Collaboration::forceCreate([
        'number' => 'S'.fake()->unique()->numerify('######'), 'company_id' => $bedrijf->id, 'location_id' => $bedrijf->locations()->first()->id,
        'creator_id' => $creator?->id, 'title' => 'Kerstlunch', 'briefing' => 'Laat de kerstlunch zien.', 'status' => $status,
        'points' => 3, 'creator_level' => 'bronze',
    ] + $velden);
}

/** Verstuurd met dit sjabloon, zonder overgebleven {placeholder} in onderwerp, push, in-app en mail (alinea's, stappen, gegevens, bedrag). */
function ketenVerstuurd(User $aan, string $sleutel, ?callable $extra = null): void
{
    Notification::assertSentTo($aan, PlatformMelding::class, function (PlatformMelding $n) use ($sleutel, $extra) {
        if ($n->template->key !== $sleutel) {
            return false;
        }
        $email = $n->template->email ?? [];
        $teksten = [
            $n->template->subject, $n->template->push, $n->template->inapp, $email['bedrag'] ?? null,
            ...($email['alineas'] ?? []), ...($email['stappen'] ?? []), ...array_column($email['gegevens'] ?? [], 1),
        ];
        foreach ($teksten as $t) {
            expect(MessageTemplate::vulIn($t, $n->vars))->not->toMatch('/\{\w+\}/');
        }

        return $extra ? $extra($n) : true;
    });
}

function ketenNietVerstuurd(User $aan, string $sleutel): void
{
    expect(Notification::sent($aan, PlatformMelding::class, fn ($n) => $n->template->key === $sleutel))->toHaveCount(0);
}

function ketenAantal(string $sleutel): int
{
    return User::all()->sum(fn ($u) => Notification::sent($u, PlatformMelding::class, fn ($n) => $n->template->key === $sleutel)->count());
}

/* ----------------------------------------------------------------- Herstellingen */

test('beoordelen: c-beoordeling met sterren, bij een laag oordeel t-oordeel-laag naar accountmanager en Super Admin', function () {
    $goed = ketenSamenwerking($this->bloesem, $this->marit, Status::Afgerond);
    $this->keten->beoordelen($goed, $this->sanne, 4, 'Prima');
    ketenVerstuurd($this->marit->user, 'c-beoordeling', fn ($n) => $n->vars['sterren'] === '4' && str_contains($n->url, "/creator/klussen/{$goed->id}"));
    expect(ketenAantal('t-oordeel-laag'))->toBe(0);

    $laag = ketenSamenwerking($this->bloesem, $this->marit, Status::Afgerond);
    $this->keten->beoordelen($laag, $this->sanne, 2, 'Te laat');
    ketenVerstuurd($this->eva, 't-oordeel-laag', fn ($n) => $n->vars['sterren'] === '2' && str_contains($n->url, "/team/samenwerkingen/{$laag->id}"));
    ketenVerstuurd($this->admin, 't-oordeel-laag');
    ketenNietVerstuurd($this->ruben, 't-oordeel-laag');
});

test('niet verschenen: creator kwam niet of zaak was er niet, elk met de eigen sjablonen', function () {
    $c = ketenSamenwerking($this->bloesem, $this->marit, Status::BezoekGepland, ['visit_at' => now()->subHour()]);
    $this->keten->nietVerschenen($c, $this->eva, 'creator');

    ketenVerstuurd($this->sanne, 'o-niet-verschenen', fn ($n) => str_contains($n->url, "/ondernemer/samenwerkingen/{$c->id}"));
    ketenNietVerstuurd($this->lid, 'o-niet-verschenen');
    ketenVerstuurd($this->marit->user, 'c-niet-verschenen', fn ($n) => $n->vars['datum'] !== '');
    ketenVerstuurd($this->eva, 't-niet-verschenen', fn ($n) => str_contains($n->url, "/team/samenwerkingen/{$c->id}"));

    $ander = ketenSamenwerking($this->bloesem, $this->marit, Status::BezoekGepland, ['visit_at' => now()->subHour()]);
    Notification::fake();
    $this->keten->nietVerschenen($ander, $this->eva, 'ondernemer');
    expect(ketenAantal('o-niet-verschenen') + ketenAantal('c-niet-verschenen'))->toBe(0);
    ketenVerstuurd($this->sanne, 'o-zaak-afwezig');
    ketenVerstuurd($this->marit->user, 'c-zaak-afwezig');
    ketenVerstuurd($this->eva, 't-niet-verschenen', fn ($n) => str_contains($n->vars['toelichting'], 'verbruikt'));
});

test('uitnodiging verlopen zonder reactie: t-geweigerd naar het koppelteam', function () {
    $c = ketenSamenwerking($this->bloesem, null, Status::CreatorZoeken);
    Invitation::forceCreate(['collaboration_id' => $c->id, 'creator_id' => $this->marit->id, 'invited_by' => $this->eva->id, 'expires_at' => now()->subMinute()]);

    expect($this->keten->verlopenUitnodigingen())->toBe(1);
    ketenVerstuurd($this->eva, 't-geweigerd', fn ($n) => $n->vars['creator'] === 'Marit Postma' && str_contains($n->url, "/team/samenwerkingen/{$c->id}"));
});

test('live: c-live altijd; met vergoeding ook c-uitbetaling-gepland met bedrag en vrijdag', function () {
    $c = ketenSamenwerking($this->bloesem, $this->sophie, Status::Goedgekeurd, ['fee_cents' => 15000, 'publish_on' => today()]);
    $this->keten->liveKoppelen($c, $this->sophie->user, 'instagram', 'https://www.instagram.com/p/abc');

    $vrijdag = today()->next('Friday')->translatedFormat('j F');
    ketenVerstuurd($this->sophie->user, 'c-live');
    ketenVerstuurd($this->sophie->user, 'c-uitbetaling-gepland', fn ($n) => $n->vars['bedrag'] === '€ 150' && $n->vars['datum'] === $vrijdag);
    ketenVerstuurd($this->lid, 'o-live');

    $gratis = ketenSamenwerking($this->bloesem, $this->marit, Status::Goedgekeurd, ['fee_cents' => 0, 'publish_on' => today()]);
    $this->keten->liveKoppelen($gratis, $this->marit->user, 'instagram', 'https://www.instagram.com/p/def');
    ketenVerstuurd($this->marit->user, 'c-live');
    ketenNietVerstuurd($this->marit->user, 'c-uitbetaling-gepland');
});

test('annuleren: o-gestopt naar de eigenaar met de juiste tegoedregel, c-gestopt naar de creator', function () {
    $c = ketenSamenwerking($this->bloesem, $this->marit, Status::BezoekGepland, ['visit_at' => now()->addDays(5)]);
    $this->keten->annuleren($c, $this->eva, 'Zaak dicht', true);
    ketenVerstuurd($this->sanne, 'o-gestopt', fn ($n) => str_contains($n->url, "/ondernemer/samenwerkingen/{$c->id}") && $n->vars['tegoed'] === 'Je tegoed staat weer klaar.');
    ketenVerstuurd($this->marit->user, 'c-gestopt', fn ($n) => str_contains($n->url, "/creator/klussen/{$c->id}"));
    ketenNietVerstuurd($this->lid, 'o-gestopt');

    $laat = ketenSamenwerking($this->bloesem, $this->marit, Status::BezoekGepland, ['visit_at' => now()->addHours(5)]);
    Notification::fake();
    $this->keten->annuleren($laat, $this->sanne, 'Toch niet', false);
    ketenVerstuurd($this->sanne, 'o-gestopt', fn ($n) => str_contains($n->vars['tegoed'], 'verbruikt'));
    ketenVerstuurd($this->marit->user, 'c-gestopt');
});

test('eigenaar-sjablonen gaan niet naar leden: o-meekijken, o-goedgekeurd, o-beoordelen', function () {
    $c = ketenSamenwerking($this->bloesem, $this->marit, Status::TerControle, ['visit_at' => now()->subDays(2)]);
    $c->versions()->create(['version' => 1, 'disk' => 'local', 'path' => 'x.mp4', 'mime' => 'video/mp4', 'size' => 1, 'caption' => 'Advertentie', 'label_check' => ['geslaagd' => true], 'submitted_by' => $this->marit->user_id]);
    $this->keten->goedkeuren($c, $this->ruben, CarbonImmutable::today()->addDay());
    ketenVerstuurd($this->sanne, 'o-goedgekeurd');
    ketenNietVerstuurd($this->lid, 'o-goedgekeurd');

    $live = ketenSamenwerking($this->bloesem, $this->marit, Status::Live, ['live_at' => now()->subDays(15), 'measure_until' => now()->subHour()]);
    $this->keten->afronden($live);
    ketenVerstuurd($this->sanne, 'o-beoordelen');
    ketenNietVerstuurd($this->lid, 'o-beoordelen');
});

/* ----------------------------------------------------------------- Nieuw aangesloten: keten */

test('t-bezoek-onbevestigd: bezoek morgen bij een zaak die zelf bevestigt, één keer, naar het planningsteam', function () {
    $planner = User::factory()->create(['name' => 'Pim Planning']);
    $planner->assignRole('Planning');
    $this->bloesem->update(['self_confirm_visits' => true]);
    $c = ketenSamenwerking($this->bloesem, $this->marit, Status::BezoekGepland, ['visit_at' => today()->addDay()->setTime(14, 30)]);
    ActivityLog::create(['subject_type' => $c->getMorphClass(), 'subject_id' => $c->id, 'event' => 'status', 'to_status' => Status::BezoekGepland->value]);

    expect($this->h->bezoekOnbevestigd())->toBe(1)->and($this->h->bezoekOnbevestigd())->toBe(0);
    ketenVerstuurd($planner, 't-bezoek-onbevestigd', fn ($n) => $n->vars['tijd'] === '14:30' && $n->vars['creator'] === 'Marit Postma');
    ketenNietVerstuurd($this->eva, 't-bezoek-onbevestigd');

    // Zonder "zelf bevestigen" staat het bezoek direct vast: geen signaal.
    $this->bloesem->update(['self_confirm_visits' => false]);
    $vast = ketenSamenwerking($this->bloesem, $this->marit, Status::BezoekGepland, ['visit_at' => today()->addDay()->setTime(11, 0)]);
    ActivityLog::create(['subject_type' => $vast->getMorphClass(), 'subject_id' => $vast->id, 'event' => 'status', 'to_status' => Status::BezoekGepland->value]);
    expect($this->h->bezoekOnbevestigd())->toBe(0);
});

test('t-link-ontbreekt: één bundel met het aantal, elke samenwerking telt één keer', function () {
    ketenSamenwerking($this->bloesem, $this->marit, Status::Goedgekeurd, ['publish_on' => today()->subDays(2)]);
    ketenSamenwerking($this->bloesem, $this->sophie, Status::Goedgekeurd, ['publish_on' => today()->subDays(3)]);
    ketenSamenwerking($this->bloesem, $this->sophie, Status::Goedgekeurd, ['publish_on' => today()]);

    expect($this->h->linkOntbreekt())->toBe(2)->and($this->h->linkOntbreekt())->toBe(0);
    ketenVerstuurd($this->ruben, 't-link-ontbreekt', fn ($n) => $n->vars['aantal'] === '2');
    expect(Notification::sent($this->ruben, PlatformMelding::class, fn ($n) => $n->template->key === 't-link-ontbreekt'))->toHaveCount(1);
});

test('o-eerste-cijfers: 24 uur na Live met views, naar de eigenaar, één keer; zonder cijfers niets', function () {
    $c = ketenSamenwerking($this->bloesem, $this->marit, Status::Live, ['live_at' => now(), 'measure_until' => now()->addDays(14)]);
    $p = $c->publications()->create(['platform' => 'instagram', 'url' => 'https://www.instagram.com/p/abc', 'posted_at' => now(), 'last_found_at' => now()]);
    $zonder = ketenSamenwerking($this->bloesem, $this->marit, Status::Live, ['live_at' => now(), 'measure_until' => now()->addDays(14)]);

    expect($this->h->eersteCijfers())->toBe(0);
    $this->travel(25)->hours();
    StatSnapshot::create(['publication_id' => $p->id, 'metric' => 'views', 'value' => 1234, 'source' => 'api', 'period' => 'totaal', 'fetched_at' => now(), 'is_estimate' => false]);

    expect($this->h->eersteCijfers())->toBe(1)->and($this->h->eersteCijfers())->toBe(0);
    ketenVerstuurd($this->sanne, 'o-eerste-cijfers', fn ($n) => $n->vars['views'] === '1.234' && $n->vars['datum'] === $c->measure_until->translatedFormat('l j F'));
    ketenNietVerstuurd($this->lid, 'o-eerste-cijfers');
    expect($zonder->fresh()->herinneringen)->toBeNull();
});

test('post niet gevonden en melden: c-post-weg naar de creator en t-post-weg naar contentcontrole, één keer per publicatie', function () {
    $c = ketenSamenwerking($this->bloesem, $this->marit, Status::Live, ['live_at' => now()->subDays(3), 'measure_until' => now()->addDays(11)]);
    $p = $c->publications()->create(['platform' => 'instagram', 'url' => 'https://www.instagram.com/p/abc', 'posted_at' => now()->subDays(3), 'last_found_at' => now()->subDays(3)]);

    $this->actingAs($this->ruben)->post(route('team.content.publicaties.controle', $p), ['found' => false, 'melden' => true])->assertSessionHasNoErrors();
    $this->actingAs($this->ruben)->post(route('team.content.publicaties.controle', $p), ['found' => false, 'melden' => true])->assertSessionHasNoErrors();

    ketenVerstuurd($this->marit->user, 'c-post-weg', fn ($n) => str_contains($n->url, "/creator/klussen/{$c->id}"));
    ketenVerstuurd($this->ruben, 't-post-weg', fn ($n) => $n->vars['creator'] === 'Marit Postma' && str_contains($n->url, "/team/samenwerkingen/{$c->id}"));
    expect(ketenAantal('c-post-weg'))->toBe(1);
});

test('handmatige cijfers goedgekeurd: c-publicatie-beoordeeld naar wie ze indiende', function () {
    $c = ketenSamenwerking($this->bloesem, $this->marit, Status::Live, ['live_at' => now()->subDays(3), 'measure_until' => now()->addDays(11)]);
    $p = $c->publications()->create(['platform' => 'instagram', 'url' => 'https://www.instagram.com/p/abc', 'posted_at' => now()->subDays(3)]);
    StatSnapshot::create(['publication_id' => $p->id, 'metric' => 'views', 'value' => 500, 'source' => StatSnapshot::HANDMATIG, 'period' => 'totaal', 'fetched_at' => now(), 'is_estimate' => false, 'entered_by' => $this->marit->user_id]);

    expect(app(Cijfers::class)->akkoord($c, $this->ruben))->toBe(1);
    ketenVerstuurd($this->marit->user, 'c-publicatie-beoordeeld', fn ($n) => $n->vars['zaak'] === 'Lunchroom Bloesem' && str_contains($n->url, "/creator/klussen/{$c->id}"));
});

/* ----------------------------------------------------------------- Nieuw aangesloten: account */

test('inloggen als: de andere Super Admins krijgen t-inloggen-als, zonder reden staat er dat er geen is', function () {
    $tweede = User::factory()->create(['name' => 'Tom Admin']);
    $tweede->assignRole('Super Admin');

    $this->actingAs($this->admin)->post(route('inloggen-als', $this->sanne))->assertRedirect(route('start'));

    ketenVerstuurd($tweede, 't-inloggen-als', fn ($n) => $n->vars['wie'] === 'Dave Admin' && $n->vars['naam'] === 'Sanne Hiemstra'
        && $n->vars['reden'] === 'geen reden opgegeven' && str_contains($n->url, '/team/logboek'));
    ketenNietVerstuurd($this->admin, 't-inloggen-als');
    ketenNietVerstuurd($this->sanne, 'g-nieuw-apparaat');
});

test('g-uitnodiging-verloopt: vijf dagen na de uitnodiging zonder gekozen wachtwoord, één keer', function () {
    $nieuw = User::factory()->unverified()->create(['name' => 'Nina Nieuw']);
    app(Uitnodiging::class)->stuur($nieuw, 'g-uitnodiging', ['naam' => 'Sanne Hiemstra', 'zaak' => 'Lunchroom Bloesem', 'rol' => 'lid']);
    ketenVerstuurd($nieuw, 'g-uitnodiging');

    $this->travel(4)->days();
    expect(app(Uitnodiging::class)->herinnerVerloopt())->toBe(0);
    $this->travel(1)->days();
    expect(app(Uitnodiging::class)->herinnerVerloopt())->toBe(1)->and(app(Uitnodiging::class)->herinnerVerloopt())->toBe(0);
    ketenVerstuurd($nieuw, 'g-uitnodiging-verloopt', fn ($n) => $n->vars['naam'] === 'Sanne Hiemstra' && $n->vars['zaak'] === 'Lunchroom Bloesem');

    // Wachtwoord al gekozen (e-mail bevestigd): geen herinnering.
    $klaar = User::factory()->unverified()->create();
    app(Uitnodiging::class)->stuur($klaar, 'g-uitnodiging', ['naam' => 'Sanne Hiemstra', 'zaak' => 'Lunchroom Bloesem', 'rol' => 'lid']);
    $klaar->markEmailAsVerified();
    $this->travel(5)->days();
    expect(app(Uitnodiging::class)->herinnerVerloopt())->toBe(0);
});

test('g-nieuw-apparaat: de eerste inlog legt vast, een ander apparaat meldt, hetzelfde apparaat niet nog eens', function () {
    $user = User::factory()->create(['name' => 'Anna Apparaat']);
    $windows = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
    $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
    $inloggen = function (string $ua) use ($user) {
        $this->withHeader('User-Agent', $ua)->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);
        auth()->logout();
    };

    $inloggen($windows);
    ketenNietVerstuurd($user, 'g-nieuw-apparaat');

    $inloggen($iphone);
    ketenVerstuurd($user, 'g-nieuw-apparaat', fn ($n) => $n->vars['apparaat'] === 'Safari op iPhone');

    $inloggen($iphone);
    $inloggen($windows);
    expect(ketenAantal('g-nieuw-apparaat'))->toBe(1)
        ->and(collect($user->fresh()->apparaten)->pluck('apparaat')->sort()->values()->all())->toBe(['Chrome op Windows', 'Safari op iPhone']);
});
