<?php

namespace App\Http\Controllers\Ondernemer;

use App\Http\Controllers\Controller;
use App\Http\Presenters\SamenwerkingPresenter;
use App\Models\Collaboration;
use App\Models\Company;
use App\Services\Samenwerking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Omgeving van de ondernemer (eigenaar of lid). */
class OndernemerController extends Controller
{
    public function __construct(private Samenwerking $dienst) {}

    public function overzicht(Request $request): Response
    {
        $bedrijf = $this->bedrijf($request);

        return Inertia::render('ondernemer/overzicht', [
            'company' => ['name' => $bedrijf->name, 'available_points' => $bedrijf->availablePoints(), 'reserved_points' => $bedrijf->reservedPoints()],
            'collaborations' => $bedrijf->collaborations()->with(['company', 'location', 'creator.user'])->latest()->get()
                ->map(fn ($c) => SamenwerkingPresenter::kort($c))->values(),
        ]);
    }

    public function aanvragen(Request $request): Response
    {
        $bedrijf = $this->bedrijf($request);

        return Inertia::render('ondernemer/aanvragen', [
            'available_points' => $bedrijf->availablePoints(),
            'locations' => $bedrijf->locations()->get(['id', 'name', 'city']),
            'levels' => collect(config('mebranding.levels'))->map(fn ($l, $k) => [
                'value' => $k, 'name' => $l['name'], 'points' => $l['points'],
                'reward' => $l['fee_cents'] > 0 ? 'Gratis bezoek en een vergoeding (betaald door MEBRANDING)' : 'Gratis bezoek',
            ])->values(),
        ]);
    }

    public function opslaan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:120'],
            'briefing' => ['required', 'string', 'max:5000'],
            'period_from' => ['nullable', 'date', 'after_or_equal:today'],
            'period_to' => ['nullable', 'date', 'after_or_equal:period_from'],
            'alcohol' => ['required', 'boolean'],
            'creator_level' => ['required', 'in:'.implode(',', array_keys(config('mebranding.levels')))],
        ]);

        $c = $this->dienst->aanvragen($this->bedrijf($request), $request->user(), $data);

        return to_route('ondernemer.samenwerking', $c)->with('melding', 'Je aanvraag is verstuurd. De punten zijn gereserveerd.');
    }

    public function samenwerking(Request $request, Collaboration $samenwerking): Response
    {
        $this->magZien($request, $samenwerking);

        return Inertia::render('ondernemer/samenwerking', [
            'collaboration' => SamenwerkingPresenter::vol($samenwerking, 'ondernemer', $request->user()),
            'can_cancel' => $request->user()->companies()->wherePivot('role', 'eigenaar')->whereKey($samenwerking->company_id)->exists(),
        ]);
    }

    public function opmerking(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        $this->magZien($request, $samenwerking);
        $data = $request->validate(['timecode' => ['nullable', 'integer', 'min:0'], 'body' => ['required', 'string', 'max:2000']]);
        $this->dienst->opmerkingOndernemer($samenwerking, $request->user(), $data['timecode'] ?? null, $data['body']);

        return back()->with('melding', 'Je opmerking is doorgegeven aan het team.');
    }

    public function annuleren(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        $this->magZien($request, $samenwerking);
        abort_unless($request->user()->companies()->wherePivot('role', 'eigenaar')->whereKey($samenwerking->company_id)->exists(), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->dienst->annuleren($samenwerking, $request->user(), $data['reason'], false);

        return back()->with('melding', 'De samenwerking is geannuleerd.');
    }

    public function beoordelen(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        $this->magZien($request, $samenwerking);
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'body' => ['nullable', 'string', 'max:2000']]);
        $this->dienst->beoordelen($samenwerking, $request->user(), $data['rating'], $data['body'] ?? null);

        return back()->with('melding', 'Dank je voor je beoordeling.');
    }

    private function bedrijf(Request $request): Company
    {
        return $request->user()->companies()->firstOrFail();
    }

    private function magZien(Request $request, Collaboration $c): void
    {
        abort_unless($request->user()->companies()->whereKey($c->company_id)->exists(), 403);
    }
}
