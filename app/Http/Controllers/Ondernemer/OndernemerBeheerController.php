<?php

namespace App\Http\Controllers\Ondernemer;

use App\Http\Controllers\Controller;
use App\Http\Presenters\BedrijfsbeheerPresenter as P;
use App\Http\Requests\OndernemerBeheer\UitnodigingRequest;
use App\Http\Requests\OndernemerBeheer\VestigingRequest;
use App\Models\Company;
use App\Models\CompanyOnboarding;
use App\Models\Location;
use App\Models\Plan;
use App\Models\User;
use App\Services\Bedrijfsbeheer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beheer door de ondernemer: vestigingen, gebruikers, abonnement en de startstappen (CLAUDE.md 5).
 * Eigenaar: alles. Lid: vestigingen en tijden bijwerken en meekijken, geen gebruikers, geen abonnement, geen bedrijfsinstellingen.
 */
class OndernemerBeheerController extends Controller
{
    public function __construct(private Bedrijfsbeheer $beheer) {}

    public function vestigingen(Request $request): Response
    {
        $bedrijf = $this->bedrijf($request);

        return Inertia::render('ondernemer/beheer/vestigingen', [
            'company' => ['name' => $bedrijf->name] + P::instellingen($bedrijf),
            'locations' => P::vestigingen($bedrijf),
            'can' => ['instellingen' => $this->isEigenaar($request, $bedrijf)],
        ]);
    }

    public function vestigingToevoegen(VestigingRequest $request): RedirectResponse
    {
        $this->beheer->vestigingOpslaan($this->bedrijf($request), null, $request->validated(), $request->user());

        return back()->with('melding', 'Vestiging toegevoegd.');
    }

    public function vestigingBijwerken(VestigingRequest $request, Location $vestiging): RedirectResponse
    {
        $bedrijf = $this->bedrijf($request);
        abort_unless($vestiging->company_id === $bedrijf->id, 404);
        $this->beheer->vestigingOpslaan($bedrijf, $vestiging, $request->validated(), $request->user());

        return back()->with('melding', 'Vestiging en bezoektijden opgeslagen.');
    }

    public function instellingen(Request $request): RedirectResponse
    {
        $bedrijf = $this->eigenaarVan($request);
        $data = $request->validate(['self_confirm_visits' => ['sometimes', 'boolean'], 'owner_approves_content' => ['sometimes', 'boolean']]);
        $this->beheer->instellingen($bedrijf, $data, $request->user());

        return back()->with('melding', 'Instelling opgeslagen.');
    }

    public function gebruikers(Request $request): Response
    {
        $bedrijf = $this->bedrijf($request);

        return Inertia::render('ondernemer/beheer/gebruikers', [
            'company' => ['name' => $bedrijf->name, 'locations' => $bedrijf->locations()->count()],
            'users' => P::gebruikers($bedrijf),
            'me' => $request->user()->id,
            'can' => ['beheren' => $this->isEigenaar($request, $bedrijf)],
        ]);
    }

    public function uitnodigen(UitnodigingRequest $request): RedirectResponse
    {
        $bedrijf = $this->eigenaarVan($request);
        $data = $request->validated();
        $this->beheer->uitnodigen($bedrijf, $data['name'] ?? null, $data['email'], $data['role'], $request->user());

        return back()->with('melding', 'Uitnodiging verstuurd naar '.$data['email'].'.');
    }

    public function opnieuwUitnodigen(Request $request, User $gebruiker): RedirectResponse
    {
        $bedrijf = $this->eigenaarVan($request);
        $this->lidVan($bedrijf, $gebruiker);
        $this->beheer->opnieuwUitnodigen($bedrijf, $gebruiker, $request->user());

        return back()->with('melding', 'Uitnodiging opnieuw verstuurd.');
    }

    public function rol(Request $request, User $gebruiker): RedirectResponse
    {
        $bedrijf = $this->eigenaarVan($request);
        $this->lidVan($bedrijf, $gebruiker);
        $data = $request->validate(['role' => ['required', 'in:eigenaar,lid']]);
        $this->beheer->rolWijzigen($bedrijf, $gebruiker, $data['role'], $request->user());

        return back()->with('melding', "{$gebruiker->name} is nu ".($data['role'] === 'eigenaar' ? 'eigenaar' : 'lid').'.');
    }

    public function verwijderen(Request $request, User $gebruiker): RedirectResponse
    {
        $bedrijf = $this->eigenaarVan($request);
        $this->lidVan($bedrijf, $gebruiker);
        $this->beheer->verwijderen($bedrijf, $gebruiker, $request->user());

        return $gebruiker->is($request->user())
            ? to_route('start')
            : back()->with('melding', "{$gebruiker->name} heeft geen toegang meer tot {$bedrijf->name}.");
    }

    public function abonnement(Request $request): Response
    {
        $bedrijf = $this->bedrijf($request);

        return Inertia::render('ondernemer/beheer/abonnement', [
            'company' => ['name' => $bedrijf->name, 'available_points' => $bedrijf->availablePoints(), 'reserved_points' => $bedrijf->reservedPoints()],
            'subscription' => P::abonnement($bedrijf),
            'plans' => P::pakketten(),
            'ledger' => P::grootboek($bedrijf),
            'invoices' => P::facturen($bedrijf),
            'requests' => P::openVerzoeken($bedrijf),
            'account_manager' => $bedrijf->accountManager?->name,
            'can' => ['wijzigen' => $this->isEigenaar($request, $bedrijf)],
        ]);
    }

    public function abonnementVerzoek(Request $request): RedirectResponse
    {
        $bedrijf = $this->eigenaarVan($request);
        $data = $request->validate([
            'soort' => ['required', 'in:wijzigen,opzeggen'],
            'plan_id' => ['exclude_unless:soort,wijzigen', 'required', 'exists:plans,id'],
            'reden' => ['exclude_unless:soort,opzeggen', 'required', 'string', 'max:120'],
            'akkoord' => ['exclude_unless:soort,opzeggen', 'accepted'],
            'toelichting' => ['nullable', 'string', 'max:2000'],
        ], ['akkoord.accepted' => 'Vink aan dat je begrijpt wat er gebeurt bij opzeggen.', 'plan_id.required' => 'Kies een pakket.', 'reden.required' => 'Kies een reden.']);

        $plan = isset($data['plan_id']) ? Plan::find($data['plan_id']) : null;
        $this->beheer->abonnementVerzoek($bedrijf, $request->user(), $data['soort'], $plan, $data['reden'] ?? null, $data['toelichting'] ?? null);
        $wie = $bedrijf->accountManager?->name ?? 'je accountmanager';

        return back()->with('melding', $data['soort'] === 'opzeggen'
            ? "Je opzegverzoek is verstuurd naar {$wie}. Je hoort binnen 1 werkdag van ons."
            : "Je verzoek om over te stappen naar {$plan?->name} is verstuurd naar {$wie}.");
    }

    public function welkom(Request $request): Response
    {
        $bedrijf = $this->bedrijf($request);
        $o = CompanyOnboarding::voor($bedrijf);

        return Inertia::render('ondernemer/beheer/welkom', [
            'company' => ['name' => $bedrijf->name, 'available_points' => $bedrijf->availablePoints(), 'locations' => $bedrijf->locations()->pluck('name')],
            'subscription' => P::abonnement($bedrijf),
            'account_manager' => $bedrijf->accountManager?->name,
            'steps' => $o->stand(),
            'closed' => $o->klaar(),
            'can' => ['team' => $this->isEigenaar($request, $bedrijf)],
        ]);
    }

    public function stap(Request $request, string $stap): RedirectResponse
    {
        abort_unless(array_key_exists($stap, CompanyOnboarding::STAPPEN), 404);
        CompanyOnboarding::vink($this->bedrijf($request), $stap);

        return back();
    }

    public function welkomSluiten(Request $request): RedirectResponse
    {
        CompanyOnboarding::voor($this->bedrijf($request))->update(['closed_at' => now()]);

        return to_route('ondernemer.overzicht');
    }

    private function bedrijf(Request $request): Company
    {
        return $request->user()->companies()->firstOrFail();
    }

    private function isEigenaar(Request $request, Company $bedrijf): bool
    {
        return $this->beheer->rolVan($bedrijf, $request->user()) === 'eigenaar';
    }

    /** Alleen een eigenaar regelt gebruikers, abonnement en bedrijfsinstellingen. */
    private function eigenaarVan(Request $request): Company
    {
        $bedrijf = $this->bedrijf($request);
        abort_unless($this->isEigenaar($request, $bedrijf), 403, 'Alleen een eigenaar van het bedrijf mag dit.');

        return $bedrijf;
    }

    private function lidVan(Company $bedrijf, User $gebruiker): void
    {
        abort_unless($bedrijf->users()->whereKey($gebruiker->id)->exists(), 404);
    }
}
