<?php

namespace App\Http\Controllers\Ondernemer;

use App\Http\Controllers\Controller;
use App\Http\Presenters\SamenwerkingPresenter;
use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Creator;
use App\Models\Status;
use App\Services\Matchlijst;
use App\Services\Samenwerking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Omgeving van de ondernemer (eigenaar of lid). */
class OndernemerController extends Controller
{
    public function __construct(private Samenwerking $dienst, private Matchlijst $matchlijst) {}

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
            'creators' => $this->matchlijst->voorOndernemer(),
        ]);
    }

    public function opslaan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'keuze' => ['nullable', 'in:team,zelf'],
            'creator_id' => ['nullable', 'required_if:keuze,zelf', 'integer'],
            'location_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:120'],
            'briefing' => ['required', 'string', 'max:5000'],
            'period_from' => ['nullable', 'date', 'after_or_equal:today'],
            'period_to' => ['nullable', 'date', 'after_or_equal:period_from'],
            'alcohol' => ['required', 'boolean'],
            'creator_level' => ['nullable', 'required_unless:keuze,zelf', 'in:'.implode(',', array_keys(config('mebranding.levels')))],
        ], ['creator_id.required_if' => 'Kies een creator, of laat MEBRANDING kiezen.', 'creator_level.required_unless' => 'Kies een niveau.']);

        // Zelf gekozen: het niveau (en dus de prijs in punten) is dat van de creator.
        $creator = ($data['keuze'] ?? 'team') === 'zelf' ? Creator::where('status', 'actief')->findOrFail($data['creator_id']) : null;
        if ($creator) {
            $data['creator_level'] = $creator->level;
        }

        $c = $this->dienst->aanvragen($this->bedrijf($request), $request->user(), $data, $creator);

        return to_route('ondernemer.samenwerking', $c)->with('melding', $creator
            ? "Je aanvraag is verstuurd en {$creator->user->name} is uitgenodigd. De punten zijn gereserveerd."
            : 'Je aanvraag is verstuurd. De punten zijn gereserveerd.');
    }

    public function samenwerking(Request $request, Collaboration $samenwerking): Response
    {
        $this->magZien($request, $samenwerking);

        return Inertia::render('ondernemer/samenwerking', [
            'collaboration' => SamenwerkingPresenter::vol($samenwerking, 'ondernemer', $request->user()),
            'can_cancel' => $request->user()->companies()->wherePivot('role', 'eigenaar')->whereKey($samenwerking->company_id)->exists(),
            'available_points' => $samenwerking->company->availablePoints(),
            // Zelf een creator kiezen kan zolang er nog niemand is en er geen uitnodiging openstaat.
            'creators' => $this->kanKiezen($samenwerking) ? $this->matchlijst->voorOndernemer($samenwerking) : null,
            'own_invitation' => $this->eigenUitnodiging($samenwerking),
        ]);
    }

    /** De ondernemer nodigt zelf een creator uit; zegt die nee of reageert die niet, dan koppelt het team (taak). */
    public function uitnodigen(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        $this->magZien($request, $samenwerking);
        $data = $request->validate(['creator_id' => ['required', 'integer']], ['creator_id.required' => 'Kies een creator.']);
        $creator = Creator::where('status', 'actief')->findOrFail($data['creator_id']);
        $this->dienst->uitnodigen($samenwerking, $creator, $request->user());

        return back()->with('melding', "{$creator->user->name} is uitgenodigd.");
    }

    private function kanKiezen(Collaboration $c): bool
    {
        return in_array($c->status, [Status::Nieuw, Status::CreatorZoeken], true) && ! $c->openInvitation();
    }

    /** Een open uitnodiging die iemand van dit bedrijf zelf verstuurde: die naam mag de ondernemer zien. */
    private function eigenUitnodiging(Collaboration $c): ?array
    {
        $u = $c->openInvitation();
        if (! $u || ! $c->company->users()->whereKey($u->invited_by)->exists()) {
            return null;
        }

        return ['creator' => $u->creator->user->name, 'expires_at' => $u->expires_at->toIso8601String()];
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
