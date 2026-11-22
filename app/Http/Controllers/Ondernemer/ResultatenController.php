<?php

namespace App\Http\Controllers\Ondernemer;

use App\Http\Controllers\Controller;
use App\Models\Collaboration;
use App\Models\Company;
use App\Models\DeliverableVersion;
use App\Models\Invoice;
use App\Models\Publication;
use App\Services\Cijfers;
use App\Services\Maandrapport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Resultaten voor de ondernemer: cijfers per samenwerking, mediabibliotheek, maandrapport en facturen.
 * Alles alleen van het eigen bedrijf (CLAUDE.md 8: de ondernemer ziet alleen de cijfers van zijn eigen samenwerkingen).
 */
class ResultatenController extends Controller
{
    public function __construct(private Cijfers $cijfers) {}

    public function index(Request $request): Response
    {
        $bedrijf = $this->bedrijf($request);
        $samenwerkingen = Collaboration::with(['location', 'creator.user'])->where('company_id', $bedrijf->id)->whereHas('publications')->latest('live_at')->get();
        $perPublicatie = collect($this->cijfers->perPublicatie(Publication::whereIn('collaboration_id', $samenwerkingen->pluck('id'))->orderBy('posted_at')->get()));
        $perSamenwerking = $perPublicatie->groupBy('collaboration_id');

        return Inertia::render('ondernemer/resultaten/index', [
            'company' => $bedrijf->name,
            'platforms' => Cijfers::perPlatform($perPublicatie->all()),
            'metrics' => Cijfers::metrics(),
            'collaborations' => $samenwerkingen->map(fn (Collaboration $c) => [
                'id' => $c->id, 'title' => $c->title, 'number' => $c->number, 'location' => $c->location->name,
                'creator' => $c->creator?->user->name, 'status' => $c->status->label(), 'live_at' => $c->live_at?->toIso8601String(),
                'measure_until' => $c->measure_until?->toIso8601String(), 'measuring' => Cijfers::meetperiodeLoopt($c),
                'publications' => ($perSamenwerking[$c->id] ?? collect())->values(),
            ])->values(),
        ]);
    }

    /** Alle goedgekeurde video's van het bedrijf; afspelen en downloaden via de gedeelde route 'video'. */
    public function media(Request $request): Response
    {
        $bedrijf = $this->bedrijf($request);
        $versies = DeliverableVersion::with('collaboration.location', 'collaboration.creator.user', 'collaboration.publications')
            ->where('outcome', 'goedgekeurd')
            ->whereIn('collaboration_id', Collaboration::where('company_id', $bedrijf->id)->select('id'))
            ->latest('id')->get();

        return Inertia::render('ondernemer/resultaten/media', [
            'company' => $bedrijf->name,
            'videos' => $versies->map(fn (DeliverableVersion $v) => [
                'id' => $v->id, 'title' => $v->collaboration->title, 'number' => $v->collaboration->number, 'collaboration_id' => $v->collaboration_id,
                'location' => $v->collaboration->location->name, 'creator' => $v->collaboration->creator?->user->name,
                'platforms' => $v->collaboration->publications->pluck('platform')->unique()->values(),
                'live_at' => $v->collaboration->live_at?->toIso8601String(), 'approved_at' => $v->updated_at->toIso8601String(),
                'size' => $v->size, 'url' => route('video', $v), 'download_url' => route('video', [$v, 'download' => 1]),
            ])->values(),
        ]);
    }

    public function maandrapport(Request $request, Maandrapport $rapport, ?string $maand = null): Response
    {
        $bedrijf = $this->bedrijf($request);

        return Inertia::render('ondernemer/resultaten/maandrapport', [
            'rapport' => $rapport->voor($bedrijf, Maandrapport::maand($maand)),
            'maanden' => Maandrapport::maanden($bedrijf),
            'metrics' => Cijfers::metrics(),
        ]);
    }

    public function afdrukken(Request $request, Maandrapport $rapport, string $maand): View
    {
        return view('cijfers.maandrapport', ['r' => $rapport->voor($this->bedrijf($request), Maandrapport::maand($maand))]);
    }

    public function facturen(Request $request): Response
    {
        $bedrijf = $this->bedrijf($request);

        return Inertia::render('ondernemer/resultaten/facturen', [
            'company' => $bedrijf->name,
            'invoices' => Invoice::where('company_id', $bedrijf->id)->latest('issued_on')->latest('id')->get()->map(fn (Invoice $f) => [
                'id' => $f->id, 'number' => $f->number, 'description' => $f->lines[0]['omschrijving'] ?? '', 'issued_on' => $f->issued_on->toDateString(),
                'due_on' => $f->due_on->toDateString(), 'total_cents' => $f->total_cents, 'status' => $f->weergaveStatus(),
                'paid_at' => $f->paid_at?->toIso8601String(), 'url' => route('ondernemer.facturen.toon', $f),
            ])->values(),
        ]);
    }

    public function factuur(Request $request, Invoice $factuur): View
    {
        abort_unless($request->user()->companies()->whereKey($factuur->company_id)->exists(), 403);

        return view('geld.factuur', ['f' => $factuur->load('company.owners'), 'terug' => route('ondernemer.facturen')]);
    }

    private function bedrijf(Request $request): Company
    {
        return $request->user()->companies()->firstOrFail();
    }
}
