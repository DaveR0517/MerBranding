<?php

use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Creator;
use App\Models\LevelProposal;
use App\Models\Publication;
use App\Models\Score as ScoreRij;
use App\Models\ScoreEvent;
use App\Models\Status;
use App\Models\User;
use App\Services\Samenwerking;
use App\Services\Score;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const SCORE_APP = 'http://app.mebranding.test';

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
    $this->score = app(Score::class);

    // Een afgeronde samenwerking met een beoordeling, zonder de hele keten te doorlopen.
    $this->afgerond = function (Creator $creator, int $sterren, string $wanneer = '-5 days'): Collaboration {
        $bedrijf = Company::first();
        $c = Collaboration::create([
            'number' => 'T-'.uniqid(), 'company_id' => $bedrijf->id, 'location_id' => $bedrijf->locations()->first()->id,
            'creator_id' => $creator->id, 'title' => 'Test', 'briefing' => 'Test', 'status' => Status::Afgerond,
            'points' => 3, 'creator_level' => $creator->level, 'completed_at' => now()->modify($wanneer),
        ]);
        $c->review()->create(['user_id' => ($this->wie)('sanne')->id, 'rating' => $sterren]);

        return $c;
    };

    // Een samenwerking tot en met Bezoek gepland via de keten zelf.
    $this->gepland = function (Creator $creator): Collaboration {
        $keten = app(Samenwerking::class);
        $bedrijf = Company::first();
        $c = $keten->aanvragen($bedrijf, ($this->wie)('sanne'), [
            'location_id' => $bedrijf->locations()->first()->id, 'title' => 'Lunch', 'briefing' => 'Laat de lunch zien.', 'creator_level' => $creator->level,
        ]);
        $uitnodiging = $keten->uitnodigen($c, $creator, ($this->wie)('eva'));
        $keten->accepteren($uitnodiging, $creator->user, $creator->user->name, '127.0.0.1');
        $keten->tijdvakKiezen($c->fresh(), $creator->user, now()->addDay()->setTime(12, 0)->toImmutable());

        return $c->fresh();
    };
});

test('ontbrekende deelscores worden naar verhouding herverdeeld', function () {
    // Alleen Klantoordeel (20) en Betrouwbaarheid (15): samen 35, dus 20/35 en 15/35.
    $totaal = Score::weeg(['reach' => null, 'engagement' => null, 'ad_result' => null, 'quality' => 100.0, 'reliability' => 65.0]);
    expect($totaal)->toBe(round(100 * 20 / 35 + 65 * 15 / 35, 1));

    // Geen advertentie: de 20 van Advertentieresultaat gaat naar verhouding naar de andere vier (som weer 100%).
    $gewichten = Score::effectieveGewichten(['reach' => 80.0, 'engagement' => 80.0, 'ad_result' => null, 'quality' => 80.0, 'reliability' => 80.0]);
    expect(array_sum($gewichten))->toEqualWithDelta(1.0, 0.0001)
        ->and($gewichten)->not->toHaveKey('ad_result')
        ->and($gewichten['reach'])->toEqualWithDelta(25 / 80, 0.0001);
    expect(Score::weeg(['reach' => 80.0, 'engagement' => 80.0, 'ad_result' => null, 'quality' => 80.0, 'reliability' => 80.0]))->toBe(80.0);

    // Gewichten zijn instelbaar.
    config(['mebranding.score.gewichten' => ['quality' => 0]]);
    expect(Score::weeg(['quality' => 100.0, 'reliability' => 50.0]))->toBe(50.0);
});

test('bayesiaans gemiddelde onder het minimum, daarna alleen het eigen werk', function () {
    $marit = ($this->wie)('marit')->creator;

    $s = $this->score->bereken($marit);
    expect($s->total)->toBe(70.0)->and($s->jobs_count)->toBe(0)->and($this->score->isVoorlopig($s))->toBeTrue()
        ->and($s->is_new_talent)->toBeTrue();

    ($this->afgerond)($marit, 5); // klantoordeel 100, betrouwbaarheid 100
    $s = $this->score->bereken($marit);
    expect($s->total)->toBe(round((100 + 2 * 70) / 3, 1))->and($this->score->isVoorlopig($s))->toBeTrue();

    ($this->afgerond)($marit, 5);
    $s = $this->score->bereken($marit);
    expect($s->total)->toBe(100.0)->and($this->score->isVoorlopig($s))->toBeFalse();
});

test('een uitnodiging weigeren telt niet mee voor de score', function () {
    $marit = ($this->wie)('marit')->creator;
    ($this->afgerond)($marit, 4);
    ($this->afgerond)($marit, 4);
    $voor = $this->score->bereken($marit)->total;

    $keten = app(Samenwerking::class);
    $bedrijf = Company::first();
    $c = $keten->aanvragen($bedrijf, ($this->wie)('sanne'), [
        'location_id' => $bedrijf->locations()->first()->id, 'title' => 'Nog een', 'briefing' => 'Test.', 'creator_level' => 'bronze',
    ]);
    $keten->weigeren($keten->uitnodigen($c, $marit, ($this->wie)('eva')), $marit->user, 'Geen tijd');

    expect(ScoreEvent::where('creator_id', $marit->id)->count())->toBe(0);
    $na = $this->score->bereken($marit);
    expect($na->total)->toBe($voor)->and($na->jobs_count)->toBe(2);
});

test('niet verschenen door de creator telt wel, door de ondernemer niet', function () {
    $marit = ($this->wie)('marit')->creator;
    ($this->afgerond)($marit, 5);
    ($this->afgerond)($marit, 5);
    expect($this->score->bereken($marit)->reliability)->toBe(100.0);

    $c = ($this->gepland)($marit);
    $planner = User::factory()->create(['name' => 'Pim Planner']);
    $planner->assignRole('Planning');
    $this->actingAs($planner)->post(SCORE_APP."/team/samenwerkingen/{$c->id}/niet-verschenen", ['who' => 'creator'])->assertSessionHasNoErrors();
    expect($c->fresh()->status)->toBe(Status::NietVerschenen);

    $event = ScoreEvent::where('collaboration_id', $c->id)->sole();
    expect($event->kind)->toBe(ScoreEvent::NIET_GEKOMEN)->and($event->points)->toBe(-50)
        ->and($event->decided_by)->toBe($planner->id);

    $s = ScoreRij::laatste($marit->id);
    expect($s->jobs_count)->toBe(3)->and($s->reliability)->toBe(round((100 + 100 + 50) / 3, 1));

    // Was de ondernemer er niet, dan geen gevolg voor de creator.
    $d = ($this->gepland)($marit);
    app(Samenwerking::class)->nietVerschenen($d, ($this->wie)('eva'), 'ondernemer');
    expect(ScoreEvent::where('collaboration_id', $d->id)->exists())->toBeFalse();
});

test('afronden van een samenwerking werkt de score bij', function () {
    $marit = ($this->wie)('marit')->creator;
    $c = ($this->afgerond)($marit, 5);
    $c->update(['status' => Status::Live, 'measure_until' => now()->subMinute(), 'completed_at' => null]);

    app(Samenwerking::class)->rondAf();

    expect(ScoreRij::laatste($marit->id))->not->toBeNull()->and(ScoreRij::laatste($marit->id)->jobs_count)->toBe(1);
});

test('niveau verandert nooit automatisch: het systeem stelt voor, het team beslist met reden en logregel', function () {
    $marit = ($this->wie)('marit')->creator;
    foreach (range(1, 3) as $i) {
        ($this->afgerond)($marit, 5);
    }

    expect($this->score->niveaumeting())->toBe(1);
    $voorstel = LevelProposal::sole();
    expect($marit->fresh()->level)->toBe('bronze')
        ->and($voorstel->to_level)->toBe('silver')->and($voorstel->status)->toBe('open');

    // Zonder recht klassement.beheren geen besluit (Eva is accountmanager).
    $this->actingAs(($this->wie)('eva'))->post(SCORE_APP."/team/klassement/voorstellen/{$voorstel->id}", ['besluit' => 'goedkeuren', 'reden' => 'Akkoord'])->assertForbidden();
    // Zonder reden geen besluit.
    $this->actingAs(($this->wie)('ruben'))->post(SCORE_APP."/team/klassement/voorstellen/{$voorstel->id}", ['besluit' => 'goedkeuren', 'reden' => ''])->assertSessionHasErrors('reden');
    expect($marit->fresh()->level)->toBe('bronze');

    $this->actingAs(($this->wie)('ruben'))->post(SCORE_APP."/team/klassement/voorstellen/{$voorstel->id}", ['besluit' => 'goedkeuren', 'reden' => 'Drie sterke samenwerkingen'])->assertSessionHasNoErrors();

    expect($marit->fresh()->level)->toBe('silver')->and($voorstel->fresh()->status)->toBe('goedgekeurd');
    $log = ActivityLog::where('event', 'niveau_gewijzigd')->sole();
    expect($log->user_id)->toBe(($this->wie)('ruben')->id)->and($log->reason)->toBe('Drie sterke samenwerkingen')
        ->and($log->data)->toMatchArray(['van' => 'bronze', 'naar' => 'silver']);
    expect($marit->user->notifications()->get()->firstWhere('data.key', 'c-niveau-omhoog'))->not->toBeNull();

    // Nog een meting maakt geen nieuw voorstel zolang er niets verandert.
    expect($this->score->niveaumeting())->toBe(0);
});

test('omlaag pas na 2 metingen achter elkaar onder de drempel', function () {
    $this->travelTo('2026-12-01 10:00');
    $sophie = ($this->wie)('sophie')->creator; // Elite, met matige beoordelingen
    $sophie->forceFill(['created_at' => now()->subYear()])->save();
    ($this->afgerond)($sophie, 3, '2026-10-20 12:00');
    ($this->afgerond)($sophie, 3, '2026-10-25 12:00');
    ($this->afgerond)($sophie, 3, '2026-11-25 12:00');

    // Eerste meting onder de drempel (1 november): nog geen voorstel, alleen Niveau in gevaar.
    $this->score->niveaumeting(now()->setDate(2026, 11, 1));
    expect(LevelProposal::count())->toBe(0)->and($this->score->inGevaar($sophie, ScoreRij::laatste($sophie->id)))->toBeTrue();

    // Tweede meting achter elkaar (1 december): voorstel om één niveau te zakken, niveau blijft tot het team beslist.
    $this->score->niveaumeting(now());
    $voorstel = LevelProposal::sole();
    expect($voorstel->from_level)->toBe('elite')->and($voorstel->to_level)->toBe('platinum')
        ->and($sophie->fresh()->level)->toBe('elite');
});

test('pauzeren of blokkeren alleen handmatig met reden, en geblokkeerd staat niet in het klassement', function () {
    $marit = ($this->wie)('marit')->creator;
    $this->score->herberekenAlles();

    $this->actingAs(($this->wie)('ruben'))->post(SCORE_APP."/team/klassement/creators/{$marit->id}/status", ['status' => 'geblokkeerd', 'reden' => ''])->assertSessionHasErrors('reden');
    $this->actingAs(($this->wie)('ruben'))->post(SCORE_APP."/team/klassement/creators/{$marit->id}/status", ['status' => 'geblokkeerd', 'reden' => 'Herhaaldelijk niet gekomen'])->assertSessionHasNoErrors();

    expect($marit->fresh()->status)->toBe('geblokkeerd')
        ->and(ActivityLog::where('event', 'creator_status')->sole()->reason)->toBe('Herhaaldelijk niet gekomen');

    $this->actingAs(($this->wie)('ruben'))->get(SCORE_APP.'/team/klassement')->assertOk()
        ->assertInertia(fn ($p) => $p->component('team/klassement/index')->where('rijen', fn ($r) => collect($r)->pluck('creator_id')->doesntContain($marit->id)));
});

test('de schermen laden en zijn afgeschermd', function () {
    $marit = ($this->wie)('marit');
    ($this->afgerond)($marit->creator, 5);

    $this->actingAs($marit)->get(SCORE_APP.'/creator/score')->assertOk()
        ->assertInertia(fn ($p) => $p->component('creator/score/index')->where('score.voorlopig', true)->has('deelscores', 5));
    $this->actingAs($marit)->get(SCORE_APP.'/team/klassement')->assertForbidden();
    $this->actingAs(($this->wie)('eva'))->get(SCORE_APP.'/team/klassement')->assertOk()->assertInertia(fn ($p) => $p->where('can.beheren', false));
});

test('cijfers uit stat_snapshots vullen Bereik en Engagement, ongecontroleerde handmatige cijfers niet', function () {
    $marit = ($this->wie)('marit')->creator;
    $c = ($this->afgerond)($marit, 5);
    $p = Publication::create(['collaboration_id' => $c->id, 'platform' => 'instagram', 'url' => 'https://www.instagram.com/p/x/', 'posted_at' => now()->subDays(20)]);
    $rij = fn (string $metriek, int $waarde, string $bron) => DB::table('stat_snapshots')->insert([
        'publication_id' => $p->id, 'metric' => $metriek, 'value' => $waarde, 'source' => $bron, 'fetched_at' => now(), 'is_estimate' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $rij('views', 900000, 'handmatig'); // nog niet gecontroleerd: telt niet
    expect(app(Score::class)->deelscoresVan($c)['reach'])->toBeNull();

    $rij('views', 10000, 'instagram');
    $rij('likes', 500, 'instagram');
    $d = app(Score::class)->deelscoresVan($c);
    expect($d['reach'])->toBeGreaterThan(0)->toBeLessThan(100)
        ->and($d['engagement'])->toBe(50.0) // 5% interactie, 10% = 100 (voorstel)
        ->and($d['ad_result'])->toBeNull();
});
