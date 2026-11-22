<?php

namespace App\Http\Presenters;

use App\Models\Collaboration;
use App\Models\Creator;
use App\Models\Score as ScoreRij;
use App\Models\ScoreEvent;
use App\Models\Status;
use App\Services\Score;
use Illuminate\Support\Collection;

/** Gegevens voor de schermen CreatorScore en AdminKlassement. Eén plek voor de uitleg van de score. */
class ScorePresenter
{
    public function __construct(private Score $score) {}

    /**
     * Deelscores met gewicht, gewicht na herverdeling en uitleg.
     *
     * @return list<array{key: string, name: string, value: float|null, weight: float, effective: float|null, uitleg: string}>
     */
    public function deelscores(?ScoreRij $s): array
    {
        $waarden = array_combine(Score::DEELSCORES, array_map(fn ($k) => $s?->{$k}, Score::DEELSCORES));
        $effectief = Score::effectieveGewichten($waarden);

        return array_map(fn ($k) => [
            'key' => $k, 'name' => Score::NAMEN[$k], 'value' => $waarden[$k], 'weight' => Score::gewichten()[$k],
            'effective' => isset($effectief[$k]) ? round($effectief[$k] * 100, 1) : null, 'uitleg' => Score::UITLEG[$k],
        ], Score::DEELSCORES);
    }

    /** @return list<array{value: string, name: string, score: int, jobs: int, quality: int|null}> */
    public function drempels(): array
    {
        return collect(Score::drempels())->map(fn ($d, $niveau) => [
            'value' => $niveau, 'name' => config("mebranding.levels.{$niveau}.name", ucfirst($niveau)),
            'score' => (int) $d['score'], 'jobs' => (int) ($d['jobs'] ?? 0), 'quality' => isset($d['quality']) ? (int) $d['quality'] : null,
        ])->values()->all();
    }

    /**
     * Laatste score van alle creators in het klassement. Geblokkeerde creators en creators in screening staan er niet in.
     *
     * @return Collection<int, ScoreRij>
     */
    public function laatsteScores(): Collection
    {
        $laatste = ScoreRij::selectRaw('creator_id as cid, max(period_end) as pe')->groupBy('creator_id');

        return ScoreRij::joinSub($laatste, 'l', fn ($j) => $j->on('scores.creator_id', '=', 'l.cid')->on('scores.period_end', '=', 'l.pe'))
            ->whereHas('creator', fn ($q) => $q->whereNotIn('status', ['geblokkeerd', 'screening']))
            ->with('creator.user')
            ->orderByDesc('total')
            ->get(['scores.*']);
    }

    /** Verschil met de score van ongeveer een maand eerder. */
    public function trend(ScoreRij $s): ?float
    {
        $eerder = ScoreRij::where('creator_id', $s->creator_id)->where('period_end', '<=', $s->period_end->copy()->subDays(28)->toDateString())
            ->latest('period_end')->value('total');

        return $eerder === null ? null : round($s->total - (float) $eerder, 1);
    }

    /** @return list<array{date: string, total: float}> */
    public function verloop(Creator $creator): array
    {
        return ScoreRij::where('creator_id', $creator->id)->where('period_end', '>=', now()->subDays(180)->toDateString())
            ->orderBy('period_end')->get(['period_end', 'total'])
            ->groupBy(fn ($r) => $r->period_end->format('o-W')) // één punt per week
            ->map(fn ($week) => ['date' => $week->last()->period_end->toDateString(), 'total' => $week->last()->total])
            ->values()->all();
    }

    /**
     * Samenwerkingen die in de huidige periode meetellen, met hun eigen deelscores.
     *
     * @return list<array<string, mixed>>
     */
    public function klussen(Creator $creator): array
    {
        $start = now()->subDays((int) Score::instelling('periode_dagen'))->startOfDay();

        return Collaboration::with('company', 'location')->where('creator_id', $creator->id)
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('status', Status::Afgerond)->where('completed_at', '>=', $start))
                ->orWhere(fn ($q) => $q->where('status', Status::NietVerschenen)->where('updated_at', '>=', $start)))
            ->latest('updated_at')->get()
            ->filter(fn (Collaboration $c) => $c->status === Status::Afgerond || ScoreEvent::where('collaboration_id', $c->id)->where('kind', ScoreEvent::NIET_GEKOMEN)->exists())
            ->map(function (Collaboration $c) {
                $d = $this->score->deelscoresVan($c);

                return [
                    'id' => $c->id, 'company' => $c->company->name, 'location' => $c->location->name, 'status' => $c->status->label(),
                    'date' => ($c->completed_at ?? $c->updated_at)->toDateString(), 'total' => Score::weeg($d), 'deelscores' => $d,
                ];
            })->values()->all();
    }

    /** Korte naam voor het klassement dat creators zien: voornaam en de eerste letter van de achternaam. */
    public static function korteNaam(string $naam): string
    {
        $delen = preg_split('/\s+/', trim($naam)) ?: [$naam];

        return count($delen) > 1 ? $delen[0].' '.mb_substr(end($delen), 0, 1).'.' : $delen[0];
    }
}
