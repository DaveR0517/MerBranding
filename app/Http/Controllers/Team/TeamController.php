<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Presenters\SamenwerkingPresenter;
use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Creator;
use App\Models\Status;
use App\Models\Task;
use App\Services\Matchlijst;
use App\Services\Samenwerking;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/** Teamomgeving: werkvoorraad, samenwerkingen, koppelen en contentcontrole. */
class TeamController extends Controller
{
    public function __construct(private Samenwerking $dienst, private Matchlijst $matchlijst) {}

    public function werkvoorraad(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('team/werkvoorraad', [
            'tasks' => Task::open()->voor($user)->with(['collaboration.company', 'company'])->orderByRaw('due_at is null, due_at')->get()
                ->map(fn (Task $t) => [
                    'id' => $t->id, 'type' => $t->type, 'title' => $t->title, 'due_at' => $t->due_at?->toIso8601String(),
                    'overdue' => $t->due_at?->isPast() ?? false, 'collaboration_id' => $t->collaboration_id,
                    'status' => $t->collaboration ? SamenwerkingPresenter::status($t->collaboration->status) : null,
                    'url' => $this->taakUrl($t),
                ])->values(),
        ]);
    }

    /** Waar een taak naartoe linkt: de samenwerking, of het scherm van de module die de taak maakte. */
    private function taakUrl(Task $t): ?string
    {
        if ($t->collaboration_id) {
            return route('team.samenwerking', $t->collaboration_id);
        }

        [$route, $veld] = self::TAAK_ROUTES[$t->type] ?? [null, null];
        if (! $route || ! Route::has($route) || ($veld && ! $t->{$veld})) {
            return null;
        }

        return $veld ? route($route, $t->{$veld}) : route($route);
    }

    /** Taaksoorten zonder samenwerking: [route, veld van de taak als parameter]. Modules voegen hier hun taaksoort toe. */
    private const TAAK_ROUTES = [
        'screening' => ['team.screening.index', null],
        'support' => ['team.communicatie.support', null],
        'cijfers-controleren' => ['team.cijfers', null],
        'abonnement' => ['team.bedrijfsbeheer.toon', 'company_id'],
        'rechten_verlengen' => ['team.content.bedrijf', 'company_id'],
        'radar_interesse' => ['team.content.radar', null],
    ];

    public function samenwerkingen(Request $request): Response
    {
        Gate::authorize('samenwerkingen.bekijken');
        $status = $request->query('status');

        return Inertia::render('team/samenwerkingen', [
            'filter' => $status,
            'statuses' => Status::options(),
            'collaborations' => Collaboration::with(['company', 'location', 'creator.user'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest('updated_at')->limit(200)->get()
                ->map(fn ($c) => SamenwerkingPresenter::kort($c))->values(),
        ]);
    }

    public function samenwerking(Request $request, Collaboration $samenwerking): Response
    {
        Gate::authorize('samenwerkingen.bekijken');

        return Inertia::render('team/samenwerking', [
            'collaboration' => SamenwerkingPresenter::vol($samenwerking, 'team', $request->user()),
            'matches' => in_array($samenwerking->status, [Status::Nieuw, Status::CreatorZoeken], true) ? $this->matchlijst->voorTeam($samenwerking) : [],
            'can' => [
                'koppelen' => $request->user()->can('samenwerkingen.koppelen'),
                'controleren' => $request->user()->can('content.controleren'),
                'plannen' => $request->user()->can('samenwerkingen.plannen'),
            ],
        ]);
    }

    public function uitnodigen(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        Gate::authorize('samenwerkingen.koppelen');
        $data = $request->validate(['creator_id' => ['required', 'exists:creators,id']]);
        $this->dienst->uitnodigen($samenwerking, Creator::findOrFail($data['creator_id']), $request->user());

        return back()->with('melding', 'Uitnodiging verstuurd.');
    }

    public function feedback(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        Gate::authorize('content.controleren');
        $data = $request->validate([
            'points' => ['required', 'array', 'min:1'],
            'points.*.timecode' => ['nullable', 'integer', 'min:0'],
            'points.*.body' => ['required', 'string', 'max:2000'],
        ]);
        $this->dienst->feedbackSturen($samenwerking, $request->user(), array_map(fn ($p) => ['timecode' => $p['timecode'] ?? null, 'body' => $p['body']], $data['points']));

        return back()->with('melding', 'Feedback verstuurd naar de creator.');
    }

    public function goedkeuren(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        Gate::authorize('content.controleren');
        $data = $request->validate(['publish_on' => ['required', 'date']]);
        $this->dienst->goedkeuren($samenwerking, $request->user(), CarbonImmutable::parse($data['publish_on']));

        return back()->with('melding', 'Goedgekeurd. De creator en de ondernemer zijn op de hoogte.');
    }

    public function annuleren(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        Gate::authorize('samenwerkingen.koppelen');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->dienst->annuleren($samenwerking, $request->user(), $data['reason'], true);

        return back()->with('melding', 'Samenwerking geannuleerd.');
    }

    public function bezoekBevestigen(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        Gate::authorize('samenwerkingen.plannen');
        $this->dienst->bezoekBevestigen($samenwerking, $request->user());

        return back()->with('melding', 'Bezoek bevestigd. De creator kan nu de video maken en aanleveren.');
    }

    public function nietVerschenen(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        Gate::authorize('samenwerkingen.plannen');
        $data = $request->validate(['who' => ['required', 'in:creator,ondernemer']]);
        $this->dienst->nietVerschenen($samenwerking, $request->user(), $data['who']);

        return back()->with('melding', 'Vastgelegd als niet verschenen.');
    }

    public function logboek(): Response
    {
        Gate::authorize('logboek.bekijken');

        return Inertia::render('team/logboek', [
            'entries' => ActivityLog::with('user')->latest('id')->limit(300)->get()->map(fn (ActivityLog $l) => [
                'at' => $l->created_at->toIso8601String(), 'user' => $l->user?->name ?? 'Systeem', 'event' => $l->event,
                'subject' => $l->subject_type === (new Collaboration)->getMorphClass() ? Collaboration::find($l->subject_id)?->number : null,
                'collaboration_id' => $l->subject_type === (new Collaboration)->getMorphClass() ? $l->subject_id : null,
                'from' => $l->from_status ? Status::from($l->from_status)->label() : null,
                'to' => $l->to_status ? Status::from($l->to_status)->label() : null,
                'reason' => $l->reason,
            ])->values(),
        ]);
    }
}
