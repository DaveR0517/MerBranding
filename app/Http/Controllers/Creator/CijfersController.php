<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Collaboration;
use App\Models\Publication;
use App\Models\StatSnapshot;
use App\Models\Status;
use App\Services\Cijfers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** De creator voert tijdens de meetperiode handmatig cijfers in, met een screenshot (CLAUDE.md 8). */
class CijfersController extends Controller
{
    public function __construct(private Cijfers $cijfers) {}

    public function index(Request $request): Response
    {
        $creator = $request->user()->creator;
        $klussen = Collaboration::with('company', 'location')->where('creator_id', $creator->id)
            ->whereIn('status', [Status::Live, Status::Afgerond])->whereHas('publications')->latest('live_at')->limit(50)->get();

        return Inertia::render('creator/cijfers/index', [
            'collaborations' => $klussen->map(fn (Collaboration $c) => [
                'id' => $c->id, 'title' => $c->title, 'company' => $c->company->name, 'location' => $c->location->name,
                'live_at' => $c->live_at?->toIso8601String(), 'measure_until' => $c->measure_until?->toIso8601String(),
                'measuring' => Cijfers::meetperiodeLoopt($c),
                'entered' => StatSnapshot::whereIn('publication_id', $c->publications()->select('id'))->where('entered_by', $request->user()->id)->exists(),
            ])->values(),
        ]);
    }

    public function toon(Request $request, Collaboration $samenwerking): Response
    {
        $this->eigen($request, $samenwerking);
        $publicaties = $samenwerking->publications()->orderBy('posted_at')->get();
        $eigen = StatSnapshot::whereIn('publication_id', $publicaties->pluck('id'))->where('entered_by', $request->user()->id)->latest('id')->get();

        return Inertia::render('creator/cijfers/invoeren', [
            'collaboration' => [
                'id' => $samenwerking->id, 'title' => $samenwerking->title, 'company' => $samenwerking->company->name,
                'live_at' => $samenwerking->live_at?->toIso8601String(), 'measure_until' => $samenwerking->measure_until?->toIso8601String(),
                'measuring' => Cijfers::meetperiodeLoopt($samenwerking),
            ],
            'publications' => $this->cijfers->perPublicatie($publicaties),
            'history' => $eigen->groupBy(fn ($s) => $s->publication_id.'|'.$s->fetched_at->toIso8601String())->map(fn ($groep) => [
                'publication_id' => $groep->first()->publication_id, 'at' => $groep->first()->fetched_at->toIso8601String(),
                'values' => $groep->pluck('value', 'metric'), 'reviewed' => $groep->every(fn ($s) => $s->reviewed_at !== null),
                'screenshot_url' => $groep->first()->screenshot_path ? route('creator.cijfers.screenshot', $groep->first()) : null,
            ])->values(),
            'metrics' => Cijfers::metrics(),
            'measure_days' => config('mebranding.timers.measure_days'),
        ]);
    }

    public function opslaan(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        $this->eigen($request, $samenwerking);
        $data = $request->validate(
            ['publication_id' => ['required', 'integer'], 'screenshot' => ['required', 'image', 'max:'.config('geld.screenshot_max_kb')]]
            + collect(Cijfers::metrics())->mapWithKeys(fn ($l, $m) => [$m => ['nullable', 'integer', 'min:0', 'max:4000000000']])->all(),
            ['screenshot.required' => 'Voeg een screenshot toe waarop de cijfers te zien zijn.'],
        );
        $publicatie = Publication::where('collaboration_id', $samenwerking->id)->findOrFail($data['publication_id']);
        $this->cijfers->invoeren($publicatie, $request->user(), $data, $request->file('screenshot'), false);

        return back()->with('melding', 'Dank je! Je cijfers zijn opgeslagen. Het team kijkt ze na.');
    }

    public function screenshot(Request $request, StatSnapshot $snapshot): BinaryFileResponse
    {
        abort_unless($snapshot->entered_by === $request->user()->id && $snapshot->screenshot_path, 403);

        return response()->file(Storage::disk('local')->path($snapshot->screenshot_path), ['Cache-Control' => 'private, max-age=3600']);
    }

    private function eigen(Request $request, Collaboration $c): void
    {
        abort_unless($c->creator?->user_id === $request->user()->id, 403);
    }
}
