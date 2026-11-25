<?php

use App\Models\Collaboration;
use App\Models\Company;
use App\Models\CourseDay;
use App\Models\Creator;
use App\Models\CreditLedger;
use App\Models\Enrollment;
use App\Models\MessageTemplate;
use App\Models\Payout;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\StatSnapshot;
use App\Models\Status;
use App\Models\User;
use App\Notifications\PlatformMelding;
use App\Services\Academy;
use App\Services\Bewaking;
use App\Services\Geld;
use App\Services\Score;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-07 10:00')); // maandag
    Notification::fake();
    $this->seed(DemoSeeder::class);
    $this->b = app(Bewaking::class);
    $this->bloesem = Company::where('name', 'Lunchroom Bloesem')->first();
    $this->sanne = User::where('email', 'sanne@demo.mebranding.nl')->first();
    $this->eva = User::where('email', 'eva@demo.mebranding.nl')->first();
    $this->ruben = User::where('email', 'ruben@demo.mebranding.nl')->first();
    $this->marit = Creator::whereHas('user', fn ($q) => $q->where('email', 'marit@demo.mebranding.nl'))->first();
    $this->sophie = Creator::whereHas('user', fn ($q) => $q->where('email', 'sophie@demo.mebranding.nl'))->first();
    $teamlid = function (string $naam, string $rol) {
        $u = User::create(['name' => $naam, 'email' => Str::slug($naam).'@demo.mebranding.nl', 'password' => Str::password(40), 'email_verified_at' => now()]);
        $u->assignRole($rol);

        return $u;
    };
    $this->financieel = $teamlid('Fenna Financieel', 'Financieel');
    $this->academybeheer = $teamlid('Anna Academy', 'Academybeheer');
});

function bewakingKlus(Company $bedrijf, ?Creator $creator, Status $status, array $velden = []): Collaboration
{
    return Collaboration::forceCreate([
        'number' => 'S'.fake()->unique()->numerify('######'), 'company_id' => $bedrijf->id, 'location_id' => $bedrijf->locations()->first()->id,
        'creator_id' => $creator?->id, 'title' => 'Kerstlunch', 'briefing' => 'Laat de kerstlunch zien.', 'status' => $status,
        'points' => 3, 'creator_level' => 'bronze',
    ] + $velden);
}

/** Verstuurd met dit sjabloon en geen {placeholder} over in onderwerp, push, in-app en mail (alinea's, gegevens, bedrag). */
function bewaakt(User $aan, string $sleutel, ?callable $extra = null): void
{
    Notification::assertSentTo($aan, PlatformMelding::class, function (PlatformMelding $n) use ($sleutel, $extra) {
        if ($n->template->key !== $sleutel) {
            return false;
        }
        $mail = $n->template->email ?? [];
        $teksten = [$n->template->subject, $n->template->push, $n->template->inapp, ...($mail['alineas'] ?? []),
            ...array_column($mail['gegevens'] ?? [], 1), $mail['bedrag'] ?? ''];
        foreach ($teksten as $t) {
            expect(MessageTemplate::vulIn($t, $n->vars))->not->toMatch('/\{\w+\}/');
        }

        return $extra ? $extra($n) : true;
    });
}

function bewaaktAantal(string $sleutel): int
{
    return User::all()->sum(fn ($u) => Notification::sent($u, PlatformMelding::class, fn ($n) => $n->template->key === $sleutel)->count());
}

test('factuur over de vervaldatum: ondernemer de dag erna en 7 dagen later, Financieel één keer t-factuur', function () {
    $this->travelTo(CarbonImmutable::parse('2027-01-01 06:10'));
    app(Geld::class)->maandelijks();

    $this->travelTo(CarbonImmutable::parse('2027-01-16 09:05'));
    app(Geld::class)->herinneringen();
    bewaakt($this->sanne, 'o-factuur-vervallen', fn ($n) => $n->vars['bedrag'] === '€ 961,95');
    bewaakt($this->financieel, 't-factuur', fn ($n) => str_contains($n->url, '/team/financieel/facturen/'));

    $this->travelTo(CarbonImmutable::parse('2027-01-23 09:05'));
    app(Geld::class)->herinneringen();
    expect(bewaaktAantal('o-factuur-vervallen'))->toBe(4) // sanne en eva, twee keer
        ->and(bewaaktAantal('t-factuur'))->toBe(1);
});

test('tegoed: bij één resterende samenwerking één keer o-tegoed, aan het eind van de maand t-tegoed-niet-benut voor de accountmanager', function () {
    expect($this->b->tegoed())->toBe(0); // 20 punten: meer dan één samenwerking

    $this->bloesem->ledger()->create(['kind' => CreditLedger::CORRECTIE, 'points' => -16, 'reason' => 'Test']);
    expect($this->b->tegoed())->toBe(1)->and($this->b->tegoed())->toBe(0);
    bewaakt($this->sanne, 'o-tegoed', fn ($n) => $n->vars['n'] === '1' && $n->vars['pakket'] === 'Groei');

    expect($this->b->tegoedNietBenut())->toBe(0); // nog niet aan het eind van de maand
    $this->travelTo(CarbonImmutable::parse('2026-12-28 08:13'));
    expect($this->b->tegoedNietBenut())->toBe(1)->and($this->b->tegoedNietBenut())->toBe(0);
    bewaakt($this->eva, 't-tegoed-niet-benut', fn ($n) => $n->vars['aantal'] === '1' && $n->vars['dagen'] === '3');
});

test('betaalrun: t-vrijgave na samenstellen, ingehouden vergoeding geeft c-gegevens-ontbreken, uitbetaald linkt naar de vergoedingen', function () {
    $this->sophie->update(['payout_details' => 'NL91ABNA0417164300', 'vat_status' => 'kor']);
    $klus = bewakingKlus($this->bloesem, $this->sophie, Status::Live);
    Payout::forceCreate(['creator_id' => $this->sophie->id, 'collaboration_id' => $klus->id, 'amount_cents' => 16000, 'status' => 'opeisbaar', 'run_on' => '2026-12-11']);
    $zonder = bewakingKlus($this->bloesem, $this->marit, Status::Live);
    Payout::forceCreate(['creator_id' => $this->marit->id, 'collaboration_id' => $zonder->id, 'amount_cents' => 4500, 'status' => 'opeisbaar', 'run_on' => '2026-12-11']);

    expect($this->b->gegevensOntbreken())->toBe(1)->and($this->b->gegevensOntbreken())->toBe(0);
    bewaakt($this->marit->user, 'c-gegevens-ontbreken', fn ($n) => $n->vars['bedrag'] === '€ 45,00');

    $run = app(Geld::class)->samenstellen(CarbonImmutable::parse('2026-12-11'));
    expect($this->b->vrijgave())->toBe(1)->and($this->b->vrijgave())->toBe(0);
    bewaakt($this->financieel, 't-vrijgave', fn ($n) => $n->vars['aantal'] === '1' && $n->vars['bedrag'] === '€ 160,00' && $n->vars['datum'] === '11 december');

    app(Geld::class)->markeerUitbetaald($run, $this->financieel);
    bewaakt($this->sophie->user, 'c-uitbetaling-verstuurd', fn ($n) => $n->url === route('creator.profiel.vergoedingen'));
});

test('koppelingen: verloopt binnenkort en weg, eigenaren en team één keer', function () {
    $account = fn (Creator|Company $eigenaar, array $velden) => SocialAccount::forceCreate([
        'owner_type' => $eigenaar->getMorphClass(), 'owner_id' => $eigenaar->id, 'platform' => 'instagram', 'handle' => '@test', 'status' => 'gekoppeld',
    ] + $velden);
    $account($this->marit, ['expires_at' => now()->addDays(5)]);
    $account($this->bloesem, ['expires_at' => now()->subDay()]);
    $account($this->sophie, ['expires_at' => now()->addDays(30)]); // nog ver weg

    expect($this->b->koppelingen())->toBe(3)->and($this->b->koppelingen())->toBe(0);
    bewaakt($this->marit->user, 'c-koppeling-verloopt', fn ($n) => $n->vars['platform'] === 'Instagram' && $n->vars['datum'] === 'zaterdag 12 december');
    bewaakt($this->sanne, 'o-koppeling-weg');
    bewaakt($this->eva, 'o-koppeling-weg');
    bewaakt($this->ruben, 't-token', fn ($n) => $n->vars['aantal'] === '2');
    expect(bewaaktAantal('c-koppeling-verloopt') + bewaaktAantal('c-koppeling-weg'))->toBe(1);
});

test('eerste werkdag: maandoverzicht voor het bedrijf waar iets liep en maandscore voor creators', function () {
    $klus = bewakingKlus($this->bloesem, $this->sophie, Status::Live, ['live_at' => '2026-11-20 12:00']);
    $post = Publication::create(['collaboration_id' => $klus->id, 'platform' => 'instagram', 'url' => 'https://instagram.com/p/abc', 'posted_at' => '2026-11-20 12:00']);
    StatSnapshot::create(['publication_id' => $post->id, 'metric' => 'views', 'value' => 12400, 'source' => 'instagram', 'period' => 'totaal', 'fetched_at' => '2026-11-25 12:00', 'is_estimate' => false]);
    app(Score::class)->herberekenAlles();

    expect($this->b->maandoverzicht())->toBe(0)->and($this->b->maandscore())->toBe(0); // 7 december is niet de eerste werkdag
    Company::create(['name' => 'Stille Zaak']); // daar liep niets: geen maandoverzicht

    $this->travelTo(CarbonImmutable::parse('2026-12-01 08:13')); // dinsdag
    expect($this->b->maandoverzicht())->toBe(1)->and($this->b->maandoverzicht())->toBe(0);
    bewaakt($this->sanne, 'o-maandoverzicht', fn ($n) => $n->vars['maand'] === 'november' && $n->vars['aantal'] === '1' && $n->vars['views'] === '12.400'
        && $n->url === route('ondernemer.maandrapport', '2026-11'));

    expect($this->b->maandscore())->toBe(2)->and($this->b->maandscore())->toBe(0);
    bewaakt($this->sophie->user, 'c-maandscore', fn ($n) => $n->vars['maand'] === 'november' && in_array($n->vars['plek'], ['1', '2'], true));
});

test('academy: herinnering 7 dagen en 1 dag vooraf, signaal onder het minimum en bericht bij een wijziging', function () {
    $dag = CourseDay::create([
        'title' => 'Basis: video maken', 'date' => '2026-12-14', 'starts_at' => '10:00', 'ends_at' => '16:00', 'location_name' => 'De Koepel',
        'address' => 'Nieuwestad 1', 'city' => 'Leeuwarden', 'max_participants' => 12, 'price_cents' => 29500, 'exam_required' => true, 'status' => CourseDay::OPEN,
    ]);
    $deelnemer = $this->marit->user;
    Enrollment::create(['course_day_id' => $dag->id, 'user_id' => $deelnemer->id, 'status' => Enrollment::INGESCHREVEN, 'paid_status' => Enrollment::BETAALD, 'checkin_code' => Str::random(32)]);

    expect($this->b->cursusdagHerinnering())->toBe(1)->and($this->b->cursusdagHerinnering())->toBe(0);
    bewaakt($deelnemer, 'a-herinnering', fn ($n) => $n->vars['n'] === '7' && $n->vars['tijd'] === '10:00' && $n->vars['adres'] === 'De Koepel, Nieuwestad 1, Leeuwarden');

    expect($this->b->academyMinimum())->toBe(1)->and($this->b->academyMinimum())->toBe(0);
    bewaakt($this->academybeheer, 't-academy', fn ($n) => $n->vars['aantal'] === '1');

    $this->travelTo(CarbonImmutable::parse('2026-12-13 08:13'));
    expect($this->b->cursusdagHerinnering())->toBe(1);
    bewaakt($deelnemer, 'a-herinnering', fn ($n) => $n->vars['n'] === '1');

    app(Academy::class)->cursusdagBijwerken($dag, ['date' => '2026-12-15', 'starts_at' => '10:00', 'ends_at' => '16:00', 'max_participants' => 12]);
    bewaakt($deelnemer, 'a-gewijzigd', fn ($n) => $n->vars['wijziging'] === 'nieuwe datum dinsdag 15 december');
    app(Academy::class)->cursusdagBijwerken($dag->fresh(), ['starts_at' => '10:00:00', 'max_participants' => 14]); // niets voor deelnemers
    expect(bewaaktAantal('a-gewijzigd'))->toBe(1);
});
