<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Presenters\ScorePresenter;
use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Creator;
use App\Models\LevelProposal;
use App\Models\Score as ScoreRij;
use App\Models\Status;
use App\Services\Score;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AdminKlassement: ranglijst, Nieuw talent en voorstellen tot niveauwijziging.
 * Het systeem stelt voor, het team beslist (recht klassement.beheren), altijd met reden en een logregel.
 */
class KlassementController extends Controller
{
    public function __construct(private Score $score, private ScorePresenter $presenter) {}

    public function index(Request $request): Response
    {
        Gate::authorize('creators.bekijken');

        $afgerond = Collaboration::where('status', Status::Afgerond)->whereNotNull('creator_id')
            ->selectRaw('creator_id, count(*) as n')->groupBy('creator_id')->pluck('n', 'creator_id');

        $rijen = $this->presenter->laatsteScores()->values()->map(fn (ScoreRij $s, int $i) => [
            'positie' => $i + 1,
            'creator_id' => $s->creator_id,
            'name' => $s->creator->user->name,
            'level' => $s->creator->level,
            'level_name' => $s->creator->levelName(),
            'kind' => $s->creator->kind,
            'niches' => $s->creator->niches ?? [],
            'city' => $s->creator->city,
            'status' => $s->creator->status,
            'since' => $s->creator->created_at?->toDateString(),
            'total' => $s->total,
            'deelscores' => array_combine(Score::DEELSCORES, array_map(fn ($k) => $s->{$k}, Score::DEELSCORES)),
            'jobs_count' => $s->jobs_count,
            'afgerond' => (int) ($afgerond[$s->creator_id] ?? 0),
            'voorlopig' => $this->score->isVoorlopig($s),
            'new_talent' => $s->is_new_talent,
            'trend' => $this->presenter->trend($s),
            'haalbaar' => $this->score->haalbaarNiveau($s->creator, $s),
            'in_gevaar' => $this->score->inGevaar($s->creator, $s),
        ]);

        return Inertia::render('team/klassement/index', [
            'rijen' => $rijen,
            'levels' => collect(config('mebranding.levels'))->map(fn ($l, $k) => ['value' => $k, 'name' => $l['name']])->values(),
            'niches' => $rijen->pluck('niches')->flatten()->unique()->sort()->values(),
            'voorstellen' => LevelProposal::with('creator.user')->where('status', 'open')->latest()->get()->map(fn (LevelProposal $p) => [
                'id' => $p->id, 'creator' => $p->creator->user->name, 'from' => $this->naam($p->from_level), 'to' => $this->naam($p->to_level),
                'omhoog' => array_search($p->to_level, Score::niveaus(), true) > array_search($p->from_level, Score::niveaus(), true),
                'score' => $p->score, 'reason' => $p->reason, 'created_at' => $p->created_at->toIso8601String(),
            ])->values(),
            'besluiten' => ActivityLog::with('user', 'subject.user')->where('subject_type', (new Creator)->getMorphClass())
                ->whereIn('event', ['niveau_gewijzigd', 'niveauvoorstel_afgewezen', 'creator_status'])->latest('id')->limit(10)->get()
                ->map(fn (ActivityLog $l) => [
                    'id' => $l->id, 'event' => $l->event, 'creator' => $l->subject?->user?->name, 'door' => $l->user?->name,
                    'van' => $l->data['van'] ?? null, 'naar' => $l->data['naar'] ?? null, 'reason' => $l->reason, 'at' => $l->created_at->toIso8601String(),
                ])->values(),
            'geblokkeerd' => Creator::with('user')->where('status', 'geblokkeerd')->get()->map(fn (Creator $c) => ['id' => $c->id, 'name' => $c->user->name])->values(),
            'drempels' => $this->presenter->drempels(),
            'gewichten' => collect(Score::gewichten())->map(fn ($g, $k) => ['key' => $k, 'name' => Score::NAMEN[$k], 'weight' => $g])->values(),
            'instellingen' => [
                'min_jobs' => (int) Score::instelling('min_opdrachten'), 'periode_dagen' => (int) Score::instelling('periode_dagen'),
                'nieuw_talent_dagen' => (int) Score::instelling('nieuw_talent_dagen'), 'startscore' => (float) Score::instelling('startscore'),
            ],
            'herberekening' => Score::volgendeHerberekening()->toDateString(),
            'bijgewerkt' => ScoreRij::max('calculated_at'),
            'can' => ['beheren' => $request->user()->can('klassement.beheren')],
        ]);
    }

    public function besluit(Request $request, LevelProposal $voorstel): RedirectResponse
    {
        Gate::authorize('klassement.beheren');
        $data = $request->validate([
            'besluit' => ['required', Rule::in(['goedkeuren', 'afwijzen'])],
            'reden' => ['required', 'string', 'min:3', 'max:255'],
        ], ['reden.required' => 'Geef een reden. Die komt in het logboek.']);

        $this->score->besluit($voorstel, $data['besluit'] === 'goedkeuren', $data['reden'], $request->user());

        return back()->with('melding', $data['besluit'] === 'goedkeuren' ? 'Niveau aangepast en gemeld aan de creator.' : 'Voorstel afgewezen.');
    }

    public function niveau(Request $request, Creator $creator): RedirectResponse
    {
        Gate::authorize('klassement.beheren');
        $data = $request->validate([
            'level' => ['required', Rule::in(Score::niveaus())],
            'reden' => ['required', 'string', 'min:3', 'max:255'],
        ], ['reden.required' => 'Geef een reden. Die komt in het logboek.']);

        $this->score->wijzigNiveau($creator, $data['level'], $data['reden'], $request->user());

        return back()->with('melding', "Niveau van {$creator->user->name} aangepast.");
    }

    public function status(Request $request, Creator $creator): RedirectResponse
    {
        Gate::authorize('klassement.beheren');
        $data = $request->validate([
            'status' => ['required', Rule::in(['actief', 'gepauzeerd', 'geblokkeerd'])],
            'reden' => ['required', 'string', 'min:3', 'max:255'],
        ], ['reden.required' => 'Geef een reden. Die komt in het logboek.']);

        $this->score->zetStatus($creator, $data['status'], $data['reden'], $request->user());

        return back()->with('melding', "Status van {$creator->user->name}: {$data['status']}.");
    }

    private function naam(string $niveau): string
    {
        return config("mebranding.levels.{$niveau}.name", ucfirst($niveau));
    }
}
