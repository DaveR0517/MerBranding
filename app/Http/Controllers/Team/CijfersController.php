<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Presenters\SamenwerkingPresenter;
use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Publication;
use App\Models\StatSnapshot;
use App\Services\Cijfers;
use App\Services\Kerncijfers;
use App\Services\Maandrapport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Teamomgeving: eigenaarsdashboard, cijfers per publicatie (controleren en corrigeren) en maandrapporten. */
class CijfersController extends Controller
{
    public function __construct(private Cijfers $cijfers) {}

    public function dashboard(Kerncijfers $kerncijfers): Response
    {
        Gate::authorize('statistieken.bekijken');

        return Inertia::render('team/cijfers/dashboard', $kerncijfers->dashboard());
    }

    /** Publicaties met hun laatste cijfers; handmatige invoer die nog gecontroleerd moet worden staat bovenaan. */
    public function index(Request $request): Response
    {
        abort_unless(Cijfers::magTeam($request->user()), 403);
        $filter = $request->integer('samenwerking') ?: null;

        $samenwerkingen = Collaboration::with(['company', 'location', 'creator.user'])
            ->whereHas('publications')
            ->when($filter, fn ($q) => $q->whereKey($filter))
            ->latest('live_at')->limit(100)->get();
        $publicaties = Publication::whereIn('collaboration_id', $samenwerkingen->pluck('id'))->orderBy('posted_at')->get();
        $cijfers = collect($this->cijfers->perPublicatie($publicaties))->groupBy('collaboration_id');

        $handmatig = StatSnapshot::with('enteredBy')->whereIn('publication_id', $publicaties->pluck('id'))
            ->where('source', StatSnapshot::HANDMATIG)->latest('id')->limit(300)->get()->groupBy('publication_id');

        $rijen = $samenwerkingen->map(fn (Collaboration $c) => SamenwerkingPresenter::kort($c) + [
            'live_at' => $c->live_at?->toIso8601String(),
            'measure_until' => $c->measure_until?->toIso8601String(),
            'publications' => collect($cijfers[$c->id] ?? [])->map(fn ($p) => $p + [
                'history' => ($handmatig[$p['id']] ?? collect())->map(fn (StatSnapshot $s) => [
                    'id' => $s->id, 'metric' => $s->metric, 'value' => $s->value, 'at' => $s->fetched_at->toIso8601String(),
                    'by' => $s->enteredBy?->name, 'note' => $s->note, 'replaces_id' => $s->replaces_id, 'reviewed' => $s->reviewed_at !== null,
                    'screenshot_url' => $s->screenshot_path ? route('team.cijfers.screenshot', $s) : null,
                ])->values(),
            ])->values(),
        ])->sortByDesc(fn ($r) => $r['publications']->contains('unreviewed', true))->values();

        return Inertia::render('team/cijfers/index', [
            'filter' => $filter,
            'collaborations' => $rijen,
            'metrics' => Cijfers::metrics(),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function invoeren(Request $request, Publication $publicatie): RedirectResponse
    {
        abort_unless(Cijfers::magTeam($request->user()), 403);
        $data = $request->validate($this->regels() + [
            'reason' => ['required', 'string', 'max:255'],
            'screenshot' => ['nullable', 'image', 'max:'.config('geld.screenshot_max_kb')],
        ]);
        $this->cijfers->invoeren($publicatie, $request->user(), $data, $request->file('screenshot'), true, $data['reason']);

        return back()->with('melding', 'Cijfers vastgelegd. De regel staat in het logboek.');
    }

    public function corrigeren(Request $request, StatSnapshot $snapshot): RedirectResponse
    {
        abort_unless(Cijfers::magTeam($request->user()), 403);
        $data = $request->validate(['value' => ['required', 'integer', 'min:0', 'max:4000000000'], 'reason' => ['required', 'string', 'max:255']]);
        $this->cijfers->corrigeren($snapshot, (int) $data['value'], $request->user(), $data['reason']);

        return back()->with('melding', 'Cijfer gecorrigeerd. De oude waarde blijft bewaard in het logboek.');
    }

    public function akkoord(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        abort_unless(Cijfers::magTeam($request->user()), 403);
        $this->cijfers->akkoord($samenwerking, $request->user());

        return back()->with('melding', 'Cijfers gecontroleerd.');
    }

    public function screenshot(Request $request, StatSnapshot $snapshot): BinaryFileResponse
    {
        abort_unless(Cijfers::magTeam($request->user()) && $snapshot->screenshot_path, 403);

        return response()->file(Storage::disk('local')->path($snapshot->screenshot_path), ['Cache-Control' => 'private, max-age=3600']);
    }

    public function maandrapport(Request $request, Maandrapport $rapport, Company $bedrijf, ?string $maand = null): Response
    {
        Gate::authorize('statistieken.bekijken');

        return Inertia::render('team/cijfers/maandrapport', [
            'rapport' => $rapport->voor($bedrijf, Maandrapport::maand($maand)),
            'maanden' => Maandrapport::maanden($bedrijf),
            'metrics' => Cijfers::metrics(),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function maandrapportAfdrukken(Maandrapport $rapport, Company $bedrijf, string $maand): View
    {
        Gate::authorize('statistieken.bekijken');

        return view('cijfers.maandrapport', ['r' => $rapport->voor($bedrijf, Maandrapport::maand($maand))]);
    }

    /** @return array<string, list<string>> */
    private function regels(): array
    {
        return collect(Cijfers::metrics())->mapWithKeys(fn ($l, $m) => [$m => ['nullable', 'integer', 'min:0', 'max:4000000000']])->all();
    }
}
