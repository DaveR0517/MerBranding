<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Creator;
use App\Models\LevelProposal;
use App\Models\Review;
use App\Models\Score as ScoreRij;
use App\Models\ScoreEvent;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Score en niveau van creators (CLAUDE.md 7.3, hoofdstuk 8 platformwerk, docs/02 flow 5).
 *
 * - Vijf deelscores van 0 tot 100 per samenwerking, gemiddeld over de periode (90 dagen).
 * - Ontbreekt een deelscore (bijvoorbeeld geen advertentie), dan wordt het gewicht naar verhouding verdeeld.
 * - Minder dan het minimum aantal opdrachten in de periode: bayesiaans gemiddelde met de startscore, label Voorlopig.
 * - Weigeren van een uitnodiging telt nooit mee. Niet komen na toezegging wel (Betrouwbaarheid).
 * - Niveau: het systeem doet alleen een voorstel. Een teamlid beslist, met reden, en dat staat in het logboek.
 *
 * Alle waarden hieronder zijn VOORSTEL en instelbaar via config('mebranding.score.*').
 */
class Score
{
    public const DEELSCORES = ['reach', 'engagement', 'ad_result', 'quality', 'reliability'];

    /** VOORSTEL: gewichten in procenten (CLAUDE.md 7.3). */
    public const GEWICHTEN = ['reach' => 25, 'engagement' => 20, 'ad_result' => 20, 'quality' => 20, 'reliability' => 15];

    /**
     * VOORSTEL: drempels per niveau (AdminKlassement, "Niveaugrenzen voorstel"). score = 90-dagenscore,
     * jobs = afgeronde samenwerkingen in totaal, quality = minimale deelscore Klantoordeel.
     */
    public const DREMPELS = [
        'bronze' => ['score' => 0, 'jobs' => 0],
        'silver' => ['score' => 60, 'jobs' => 3],
        'gold' => ['score' => 70, 'jobs' => 6],
        'platinum' => ['score' => 80, 'jobs' => 10, 'quality' => 80],
        'elite' => ['score' => 90, 'jobs' => 15],
    ];

    /** VOORSTEL: overige instellingen. */
    public const STANDAARD = [
        'periode_dagen' => 90,
        'min_opdrachten' => 2,
        'startscore' => 70,
        'nieuw_talent_dagen' => 90,
        // Strafregeling is nog te bespreken (docs/02 flow 5): deze punten zijn een werkwaarde.
        'strafpunten' => [ScoreEvent::NIET_GEKOMEN => -50, ScoreEvent::TE_LAAT => -15, ScoreEvent::POST_WEG => -30],
        // Formule relatief bereik is een open punt: views gelijk aan het aantal volgers geeft 100.
        'bereik_ratio' => 1.0,
        // Zonder volgersaantal: logaritmische schaal waarop dit aantal views 100 geeft.
        'bereik_referentie' => 50000,
        // Interacties per view waarbij Engagement 100 is.
        'engagement_referentie' => 0.10,
        // Klikratio van een advertentie (procent) waarbij Advertentieresultaat 100 is.
        'advertentie_ctr_referentie' => 2.0,
        // Melding "Niveau in gevaar" zoveel dagen voor de herberekening (0 = uit).
        'gevaar_dagen' => 14,
    ];

    public const UITLEG = [
        'reach' => 'Views ten opzichte van je eigen volgers, dus grote accounts winnen niet automatisch.',
        'engagement' => 'Likes, reacties, shares en opgeslagen per view.',
        'ad_result' => 'Resultaat als je video als advertentie draait. Geen advertentie: telt niet mee.',
        'quality' => 'De beoordeling van de ondernemer (sterren) samen met het kwaliteitsoordeel van contentcontrole.',
        'reliability' => 'Op tijd bij het bezoek, op tijd geleverd en de post op de afgesproken datum.',
    ];

    public const NAMEN = [
        'reach' => 'Bereik', 'engagement' => 'Engagement', 'ad_result' => 'Advertentieresultaat',
        'quality' => 'Klantoordeel en kwaliteit', 'reliability' => 'Betrouwbaarheid',
    ];

    /** Reden die de keten schrijft als de creator niet kwam (App\Services\Samenwerking::nietVerschenen). */
    public const REDEN_CREATOR_KWAM_NIET = 'Creator kwam niet';

    private ?bool $heeftCijfers = null;

    /** @var list<string>|null */
    private ?array $cijferKolommen = null;

    public function __construct(private Melder $melder) {}

    /* ----------------------------------------------------------------- Instellingen */

    /** @return array<string, float> */
    public static function gewichten(): array
    {
        return array_map('floatval', array_merge(self::GEWICHTEN, (array) config('mebranding.score.gewichten', [])));
    }

    /** @return array<string, array{score: int, jobs: int, quality?: int}> */
    public static function drempels(): array
    {
        return array_replace_recursive(self::DREMPELS, (array) config('mebranding.score.drempels', []));
    }

    public static function instelling(string $sleutel): mixed
    {
        return config("mebranding.score.{$sleutel}", self::STANDAARD[$sleutel]);
    }

    /** @return list<string> */
    public static function niveaus(): array
    {
        return array_keys(config('mebranding.levels'));
    }

    /* ----------------------------------------------------------------- Rekenen */

    /**
     * Gewogen totaal; ontbrekende deelscores (null) tellen niet mee en hun gewicht wordt naar verhouding verdeeld.
     *
     * @param  array<string, float|null>  $deelscores
     */
    public static function weeg(array $deelscores, ?array $gewichten = null): ?float
    {
        $effectief = self::effectieveGewichten($deelscores, $gewichten);
        if ($effectief === []) {
            return null;
        }

        return round(array_sum(array_map(fn ($k) => $deelscores[$k] * $effectief[$k], array_keys($effectief))), 1);
    }

    /**
     * Gewichten na herverdeling, als fractie (som 1). Alleen voor deelscores met een waarde.
     *
     * @param  array<string, float|null>  $deelscores
     * @return array<string, float>
     */
    public static function effectieveGewichten(array $deelscores, ?array $gewichten = null): array
    {
        $gewichten ??= self::gewichten();
        $aanwezig = array_filter($gewichten, fn ($g, $k) => $g > 0 && isset($deelscores[$k]), ARRAY_FILTER_USE_BOTH);
        $som = array_sum($aanwezig);

        return $som > 0 ? array_map(fn ($g) => $g / $som, $aanwezig) : [];
    }

    /**
     * Deelscores van één samenwerking. Niet van toepassing of geen gegevens: null.
     *
     * @return array<string, float|null>
     */
    public function deelscoresVan(Collaboration $c): array
    {
        $uit = array_fill_keys(self::DEELSCORES, null);
        $events = ScoreEvent::where('collaboration_id', $c->id)->get();
        $strafpunten = (int) $events->whereIn('kind', [ScoreEvent::NIET_GEKOMEN, ScoreEvent::TE_LAAT, ScoreEvent::POST_WEG])->sum('points');
        $uit['reliability'] = (float) max(0, min(100, 100 + $strafpunten));

        if ($c->status !== Status::Afgerond) {
            return $uit; // Niet verschenen door de creator: alleen Betrouwbaarheid telt.
        }

        $cijfers = $this->cijfers($c);
        $views = $cijfers['views'] ?? null;

        if ($views !== null) {
            $volgers = $cijfers['followers'] ?? null;
            $uit['reach'] = $volgers > 0
                ? min(100, 100 * ($views / $volgers) / (float) self::instelling('bereik_ratio'))
                : min(100, 100 * log10(1 + $views) / log10(1 + (float) self::instelling('bereik_referentie')));

            if ($views > 0) {
                $interacties = array_sum(array_intersect_key($cijfers, array_flip(['likes', 'comments', 'shares', 'saves'])));
                $uit['engagement'] = min(100, 100 * ($interacties / $views) / (float) self::instelling('engagement_referentie'));
            }
        }

        $ctr = $cijfers['ad_ctr'] ?? (isset($cijfers['ad_clicks'], $cijfers['ad_impressions']) && $cijfers['ad_impressions'] > 0
            ? 100 * $cijfers['ad_clicks'] / $cijfers['ad_impressions'] : null);
        if ($ctr !== null) {
            $uit['ad_result'] = min(100, 100 * $ctr / (float) self::instelling('advertentie_ctr_referentie'));
        }

        $oordelen = $events->where('kind', ScoreEvent::BEOORDELING)->pluck('points')->map(fn ($p) => (float) $p);
        $review = Review::where('collaboration_id', $c->id)->value('rating');
        if ($review !== null) {
            $oordelen->push((float) $review * 20); // 5 van 5 sterren = 100
        }
        $uit['quality'] = $oordelen->isEmpty() ? null : $oordelen->avg();

        return array_map(fn ($v) => $v === null ? null : round($v, 1), $uit);
    }

    /** Bereken en bewaar de score van een creator over de periode die eindigt op $op (standaard vandaag). */
    public function bereken(Creator $creator, ?CarbonInterface $op = null): ScoreRij
    {
        $eind = CarbonImmutable::parse($op ?? now())->endOfDay();
        $start = $eind->subDays((int) self::instelling('periode_dagen'))->startOfDay();
        $klussen = $this->klussen($creator, $start, $eind);

        $perKlus = $klussen->map(fn (Collaboration $c) => $this->deelscoresVan($c));
        $deelscores = [];
        foreach (self::DEELSCORES as $k) {
            $waarden = $perKlus->pluck($k)->filter(fn ($v) => $v !== null);
            $deelscores[$k] = $waarden->isEmpty() ? null : round($waarden->avg(), 1);
        }

        $n = $klussen->count();
        $min = (int) self::instelling('min_opdrachten');
        $ruw = self::weeg($deelscores);
        $totaal = $n >= $min && $ruw !== null
            ? $ruw
            : round(($n * ($ruw ?? 0) + $min * $this->prior($creator, $eind)) / ($n + $min), 1);

        // Eén rij per creator per dag (whereDate, omdat de datumkolom per database anders wordt opgeslagen).
        $rij = ScoreRij::where('creator_id', $creator->id)->whereDate('period_end', $eind->toDateString())->first()
            ?? new ScoreRij(['creator_id' => $creator->id, 'period_end' => $eind->toDateString()]);
        $rij->fill($deelscores + [
            'period_start' => $start->toDateString(), 'total' => $totaal, 'jobs_count' => $n,
            'is_new_talent' => $this->isNieuwTalent($creator, $eind), 'calculated_at' => now(),
        ])->save();

        return $rij;
    }

    /** Dagelijks: scores van alle creators die door de screening zijn. */
    public function herberekenAlles(?CarbonInterface $op = null): int
    {
        $creators = Creator::where('status', '!=', 'screening')->get();
        $creators->each(fn (Creator $c) => $this->bereken($c, $op));

        return $creators->count();
    }

    public function isNieuwTalent(Creator $creator, ?CarbonInterface $op = null): bool
    {
        return $creator->created_at !== null
            && $creator->created_at->gt(CarbonImmutable::parse($op ?? now())->subDays((int) self::instelling('nieuw_talent_dagen')));
    }

    public function isVoorlopig(ScoreRij $s): bool
    {
        return $s->jobs_count < (int) self::instelling('min_opdrachten');
    }

    /**
     * Samenwerkingen die meetellen in de periode: afgerond, plus samenwerkingen waar de creator niet kwam.
     * Uitnodigingen (ook geweigerde of verlopen) tellen nooit mee: die staan niet in deze selectie.
     *
     * @return Collection<int, Collaboration>
     */
    private function klussen(Creator $creator, CarbonInterface $start, CarbonInterface $eind): Collection
    {
        $nietGekomen = ScoreEvent::where('creator_id', $creator->id)->where('kind', ScoreEvent::NIET_GEKOMEN)
            ->whereBetween('created_at', [$start, $eind])->pluck('collaboration_id')->filter();

        return Collaboration::where(function ($q) use ($creator, $start, $eind) {
            $q->where('creator_id', $creator->id)->where('status', Status::Afgerond)->whereBetween('completed_at', [$start, $eind]);
        })->orWhereIn('id', $nietGekomen)->get();
    }

    /** Uitgangspunt van het bayesiaanse gemiddelde: de laatste volwaardige score, anders de startscore. */
    private function prior(Creator $creator, CarbonInterface $voor): float
    {
        $vorige = ScoreRij::where('creator_id', $creator->id)->where('period_end', '<', $voor->toDateString())
            ->where('jobs_count', '>=', (int) self::instelling('min_opdrachten'))->latest('period_end')->value('total');

        return (float) ($vorige ?? self::instelling('startscore'));
    }

    /**
     * Laatste waarde per publicatie en metriek uit stat_snapshots (module cijfers), opgeteld over de publicaties.
     * Schattingen, vervangen regels en ongecontroleerde handmatige cijfers tellen niet mee. Ontbreekt de tabel nog, dan zijn er geen cijfers.
     *
     * @return array<string, float>
     */
    private function cijfers(Collaboration $c): array
    {
        $this->heeftCijfers ??= Schema::hasTable('stat_snapshots');
        if (! $this->heeftCijfers) {
            return [];
        }

        $this->cijferKolommen ??= Schema::getColumnListing('stat_snapshots');
        $heeft = fn (string $kolom) => in_array($kolom, $this->cijferKolommen, true);

        $rijen = DB::table('stat_snapshots')
            ->join('publications', 'publications.id', '=', 'stat_snapshots.publication_id')
            ->where('publications.collaboration_id', $c->id)
            ->where(fn ($q) => $q->where('stat_snapshots.is_estimate', false)->orWhereNull('stat_snapshots.is_estimate'))
            // Door een correctie van het team vervangen regels tellen niet.
            ->when($heeft('replaces_id'), fn ($q) => $q->whereNotIn('stat_snapshots.id', DB::table('stat_snapshots')->whereNotNull('replaces_id')->select('replaces_id')))
            // Handmatige cijfers tellen pas na controle door het team (docs/02 flow 5, uitzonderingen).
            ->when($heeft('reviewed_at'), fn ($q) => $q->where(fn ($q) => $q->where('stat_snapshots.source', '!=', 'handmatig')->orWhereNotNull('stat_snapshots.reviewed_at')))
            ->orderBy('stat_snapshots.fetched_at')->orderBy('stat_snapshots.id')
            ->get(['stat_snapshots.publication_id', 'stat_snapshots.metric', 'stat_snapshots.value']);

        $laatste = [];
        foreach ($rijen as $r) {
            $laatste[$r->publication_id][$r->metric] = (float) $r->value;
        }

        $som = [];
        foreach ($laatste as $metrieken) {
            foreach ($metrieken as $metriek => $waarde) {
                $som[$metriek] = ($som[$metriek] ?? 0) + $waarde;
            }
        }

        return $som;
    }

    /* ----------------------------------------------------------------- Gebeurtenissen */

    /**
     * Leg een gebeurtenis vast die de score verklaart en reken de score opnieuw uit. Eén keer per samenwerking,
     * soort en reden. Ook te gebruiken door andere modules (bijvoorbeeld post_weg bij W8).
     */
    public function registreer(Collaboration $c, string $soort, string $reden, ?User $door = null, ?int $punten = null): ScoreEvent
    {
        abort_unless(in_array($soort, [ScoreEvent::NIET_GEKOMEN, ScoreEvent::TE_LAAT, ScoreEvent::POST_WEG, ScoreEvent::BEOORDELING], true), 422);
        abort_unless($c->creator_id, 422);

        $event = ScoreEvent::firstOrCreate(
            ['collaboration_id' => $c->id, 'kind' => $soort, 'reason' => $reden],
            ['creator_id' => $c->creator_id, 'points' => $punten ?? (int) (self::instelling('strafpunten')[$soort] ?? 0), 'decided_by' => $door?->id],
        );

        $this->bereken($c->creator);

        return $event;
    }

    /* ----------------------------------------------------------------- Niveau */

    /** Hoogste niveau waarvan alle drempels gehaald zijn (VOORSTEL-drempels). */
    public function haalbaarNiveau(Creator $creator, ScoreRij $s): string
    {
        $afgerond = Collaboration::where('creator_id', $creator->id)->where('status', Status::Afgerond)->count();
        $haalbaar = self::niveaus()[0];

        foreach (self::drempels() as $niveau => $d) {
            $ok = $s->total >= $d['score'] && $afgerond >= ($d['jobs'] ?? 0)
                && (! isset($d['quality']) || ($s->quality !== null && $s->quality >= $d['quality']));
            if ($ok) {
                $haalbaar = $niveau;
            }
        }

        return $haalbaar;
    }

    public function inGevaar(Creator $creator, ScoreRij $s): bool
    {
        return ! $this->isVoorlopig($s) && $this->rang($this->haalbaarNiveau($creator, $s)) < $this->rang($creator->level);
    }

    /** VOORSTEL: herberekening op de 1e van de maand. */
    public static function volgendeHerberekening(?CarbonInterface $op = null): CarbonImmutable
    {
        return CarbonImmutable::parse($op ?? now())->startOfMonth()->addMonthNoOverflow();
    }

    /**
     * Niveaumeting (1e van de maand): maakt voorstellen, verandert zelf nooit een niveau.
     * Omhoog: zodra alle drempels van een hoger niveau gehaald zijn. Omlaag: pas na 2 metingen achter elkaar onder de drempel.
     */
    public function niveaumeting(?CarbonInterface $op = null): int
    {
        $op = CarbonImmutable::parse($op ?? now());
        $vorigeMeting = $op->startOfMonth()->subMonthNoOverflow();
        $aantal = 0;

        foreach (Creator::where('status', 'actief')->get() as $creator) {
            $s = $this->bereken($creator, $op);
            if ($this->isVoorlopig($s)) {
                continue;
            }

            $haalbaar = $this->haalbaarNiveau($creator, $s);
            $nu = $this->rang($creator->level);

            if ($this->rang($haalbaar) > $nu) {
                $aantal += (int) $this->stelVoor($creator, $haalbaar, $s, "90-dagenscore {$this->getal($s->total)} haalt alle drempels van ".$this->naam($haalbaar).'.');
            } elseif ($this->rang($haalbaar) < $nu) {
                $vorige = ScoreRij::where('creator_id', $creator->id)->whereDate('period_end', $vorigeMeting->toDateString())->first();
                if ($vorige && ! $this->isVoorlopig($vorige) && $this->rang($this->haalbaarNiveau($creator, $vorige)) < $nu) {
                    $omlaag = self::niveaus()[$nu - 1];
                    $aantal += (int) $this->stelVoor($creator, $omlaag, $s, "2 metingen achter elkaar onder de drempel van {$creator->levelName()} ({$this->getal($vorige->total)} en {$this->getal($s->total)}).");
                }
            }
        }

        return $aantal;
    }

    /** Melding "Niveau in gevaar", een vast aantal dagen voor de herberekening (VOORSTEL 14, 0 = uit). */
    public function gevaarMeldingen(?CarbonInterface $op = null): int
    {
        $op = CarbonImmutable::parse($op ?? now());
        $dagen = (int) self::instelling('gevaar_dagen');
        $herberekening = self::volgendeHerberekening($op);
        if ($dagen < 1 || ! $op->isSameDay($herberekening->subDays($dagen))) {
            return 0;
        }

        $aantal = 0;
        foreach (Creator::where('status', 'actief')->with('user')->get() as $creator) {
            $s = ScoreRij::laatste($creator->id) ?? $this->bereken($creator, $op);
            if ($this->inGevaar($creator, $s)) {
                $this->melder->stuur('c-niveau-gevaar', $creator->user, ['niveau' => $creator->levelName()], route('creator.score'));
                $this->log($creator, null, 'niveau_in_gevaar', null, ['score' => $s->total, 'herberekening' => $herberekening->toDateString()]);
                $aantal++;
            }
        }

        return $aantal;
    }

    /** Besluit van het team over een voorstel. Alleen met recht klassement.beheren (controle in de controller). */
    public function besluit(LevelProposal $voorstel, bool $goedkeuren, string $reden, User $door): void
    {
        if ($voorstel->status !== 'open') {
            throw ValidationException::withMessages(['voorstel' => 'Over dit voorstel is al besloten.']);
        }

        DB::transaction(function () use ($voorstel, $goedkeuren, $reden, $door) {
            $voorstel->update([
                'status' => $goedkeuren ? 'goedgekeurd' : 'afgewezen', 'decided_by' => $door->id,
                'decided_at' => now(), 'decision_reason' => $reden,
            ]);
            Task::where('type', 'niveauvoorstel')->where('creator_id', $voorstel->creator_id)->whereNull('closed_at')->update(['closed_at' => now()]);

            if ($goedkeuren) {
                $this->wijzigNiveau($voorstel->creator, $voorstel->to_level, $reden, $door, $voorstel);
            } else {
                $this->log($voorstel->creator, $door, 'niveauvoorstel_afgewezen', $reden, ['voorstel' => $voorstel->id, 'van' => $voorstel->from_level, 'naar' => $voorstel->to_level]);
            }
        });
    }

    /** Niveau wijzigen: alleen door een teamlid, altijd met reden en een logregel. Raakt lopende samenwerkingen niet. */
    public function wijzigNiveau(Creator $creator, string $naar, string $reden, User $door, ?LevelProposal $voorstel = null): void
    {
        if (! in_array($naar, self::niveaus(), true)) {
            throw ValidationException::withMessages(['level' => 'Onbekend niveau.']);
        }
        $van = $creator->level;
        if ($van === $naar) {
            throw ValidationException::withMessages(['level' => "{$creator->levelName()} is al het huidige niveau."]);
        }

        $creator->update(['level' => $naar]);
        $this->log($creator, $door, 'niveau_gewijzigd', $reden, ['van' => $van, 'naar' => $naar, 'voorstel' => $voorstel?->id]);
        LevelProposal::where('creator_id', $creator->id)->where('status', 'open')->whereKeyNot($voorstel?->id)->update(['status' => 'vervallen']);

        $sjabloon = $this->rang($naar) > $this->rang($van) ? 'c-niveau-omhoog' : 'c-niveau-omlaag';
        $this->melder->stuur($sjabloon, $creator->user, ['niveau' => $this->naam($naar)], route('creator.score'));
    }

    /** Pauzeren of blokkeren: nooit automatisch, altijd handmatig met reden (platformwerk). */
    public function zetStatus(Creator $creator, string $status, string $reden, User $door): void
    {
        if (! in_array($status, ['actief', 'gepauzeerd', 'geblokkeerd'], true)) {
            throw ValidationException::withMessages(['status' => 'Onbekende status.']);
        }
        $van = $creator->status;
        $creator->update(['status' => $status]);
        $this->log($creator, $door, 'creator_status', $reden, ['van' => $van, 'naar' => $status]);
    }

    /* ----------------------------------------------------------------- Intern */

    private function stelVoor(Creator $creator, string $naar, ScoreRij $s, string $reden): bool
    {
        $open = LevelProposal::where('creator_id', $creator->id)->where('status', 'open')->first();
        if ($open?->to_level === $naar) {
            return false;
        }
        $open?->update(['status' => 'vervallen']);

        LevelProposal::create(['creator_id' => $creator->id, 'from_level' => $creator->level, 'to_level' => $naar, 'score' => $s->total, 'reason' => $reden]);
        Task::firstOrCreate(
            ['type' => 'niveauvoorstel', 'creator_id' => $creator->id, 'closed_at' => null],
            ['title' => "Niveauvoorstel {$creator->user->name}: {$creator->levelName()} naar {$this->naam($naar)}", 'permission' => 'klassement.beheren'],
        );
        $this->log($creator, null, 'niveauvoorstel', $reden, ['van' => $creator->level, 'naar' => $naar, 'score' => $s->total]);

        return true;
    }

    private function log(Creator $creator, ?User $door, string $event, ?string $reden, array $data): void
    {
        ActivityLog::create([
            'user_id' => $door?->id, 'subject_type' => $creator->getMorphClass(), 'subject_id' => $creator->id,
            'event' => $event, 'reason' => $reden, 'data' => $data,
        ]);
    }

    private function rang(string $niveau): int
    {
        return (int) array_search($niveau, self::niveaus(), true);
    }

    private function naam(string $niveau): string
    {
        return config("mebranding.levels.{$niveau}.name", ucfirst($niveau));
    }

    private function getal(float $x): string
    {
        return number_format($x, 1, ',', '.');
    }
}
