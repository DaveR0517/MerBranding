<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Http\Presenters\ScorePresenter;
use App\Models\Score as ScoreRij;
use App\Models\ScoreEvent;
use App\Services\Score;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** CreatorScore: de eigen score, uitlegbaar (platformwerk): deelscores, gewichten, wat telt en wat niet. */
class ScoreController extends Controller
{
    public function __construct(private Score $score, private ScorePresenter $presenter) {}

    public function index(Request $request): Response
    {
        $creator = $request->user()->creator()->with('user')->firstOrFail();
        // Vandaag opnieuw uitrekenen, zodat een nieuwe beoordeling direct zichtbaar is.
        $s = $this->score->bereken($creator);

        $niveaus = Score::niveaus();
        $drempels = $this->presenter->drempels();
        $huidig = (int) array_search($creator->level, $niveaus, true);
        $volgende = $drempels[$huidig + 1] ?? null;
        $ondergrens = $drempels[$huidig]['score'];

        $ranglijst = $this->presenter->laatsteScores()
            ->when($s->is_new_talent, fn ($r) => $r->where('is_new_talent', true))->values();
        $positie = $ranglijst->search(fn (ScoreRij $r) => $r->creator_id === $creator->id);

        $deelscores = $this->presenter->deelscores($s);
        $laagste = collect($deelscores)->filter(fn ($d) => $d['value'] !== null)->sortBy('value')->first();

        return Inertia::render('creator/score/index', [
            'creator' => ['name' => $creator->user->name, 'first_name' => strtok($creator->user->name, ' '), 'level' => $creator->levelName(), 'since' => $creator->created_at?->toDateString()],
            'score' => [
                'total' => $s->total, 'jobs_count' => $s->jobs_count, 'voorlopig' => $this->score->isVoorlopig($s),
                'min_jobs' => (int) Score::instelling('min_opdrachten'), 'startscore' => (float) Score::instelling('startscore'),
                'new_talent' => $s->is_new_talent, 'new_talent_until' => $creator->created_at?->addDays((int) Score::instelling('nieuw_talent_dagen'))->toDateString(),
                'period_days' => (int) Score::instelling('periode_dagen'), 'calculated_at' => $s->calculated_at->toIso8601String(),
            ],
            'deelscores' => $deelscores,
            'next_level' => $volgende ? [
                'name' => $volgende['name'], 'score' => $volgende['score'], 'jobs' => $volgende['jobs'],
                'points_to_go' => max(0, round($volgende['score'] - $s->total, 1)),
                'progress' => $volgende['score'] > $ondergrens ? (int) max(0, min(100, round(($s->total - $ondergrens) / ($volgende['score'] - $ondergrens) * 100))) : 100,
            ] : null,
            'gevaar' => $this->score->inGevaar($creator, $s) ? [
                'herberekening' => Score::volgendeHerberekening()->toDateString(), 'drempel' => $ondergrens,
                'laagste' => $laagste ? "{$laagste['name']}, ".number_format($laagste['value'], 0) : null,
            ] : null,
            'verloop' => $this->presenter->verloop($creator),
            'klussen' => $this->presenter->klussen($creator),
            'events' => ScoreEvent::with('collaboration.company')->where('creator_id', $creator->id)->latest()->limit(20)->get()
                ->map(fn (ScoreEvent $e) => [
                    'id' => $e->id, 'kind' => $e->kind, 'label' => ScoreEvent::label($e->kind), 'reason' => $e->reason, 'points' => $e->points,
                    'company' => $e->collaboration?->company?->name, 'date' => $e->created_at->toDateString(),
                ])->values(),
            'klassement' => [
                'soort' => $s->is_new_talent ? 'Nieuw talent' : 'Alle creators',
                'positie' => $positie === false ? null : $positie + 1,
                'aantal' => $ranglijst->count(),
                'top' => $ranglijst->take(5)->map(fn (ScoreRij $r, $i) => [
                    'positie' => $i + 1, 'naam' => $r->creator_id === $creator->id ? 'Jij' : ScorePresenter::korteNaam($r->creator->user->name),
                    'score' => $r->total, 'ik' => $r->creator_id === $creator->id,
                ])->values(),
            ],
            'drempels' => $drempels,
            'herberekening' => Score::volgendeHerberekening()->toDateString(),
        ]);
    }
}
