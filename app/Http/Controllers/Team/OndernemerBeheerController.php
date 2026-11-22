<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Presenters\BedrijfsbeheerPresenter as P;
use App\Http\Requests\OndernemerBeheer\UitnodigingRequest;
use App\Http\Requests\OndernemerBeheer\VestigingRequest;
use App\Models\Company;
use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use App\Services\Bedrijfsbeheer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Detail van een bedrijf voor het team: gegevens, vestigingen en tijden, gebruikers, tegoed en accountmanager. */
class OndernemerBeheerController extends Controller
{
    public function __construct(private Bedrijfsbeheer $beheer) {}

    public function bedrijf(Company $bedrijf): Response
    {
        Gate::authorize('bedrijven.bekijken');

        return Inertia::render('team/ondernemer-beheer/bedrijf', [
            'company' => $bedrijf->only(['id', 'name', 'kvk', 'branche', 'email', 'phone', 'account_manager_id']) + P::instellingen($bedrijf) + [
                'available_points' => $bedrijf->availablePoints(), 'reserved_points' => $bedrijf->reservedPoints(),
            ],
            'locations' => P::vestigingen($bedrijf),
            'users' => P::gebruikers($bedrijf),
            'ledger' => P::grootboek($bedrijf),
            'subscription' => P::abonnement($bedrijf),
            'requests' => P::openVerzoeken($bedrijf),
            'managers' => $this->teamleden()->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'can' => ['beheren' => Gate::allows('bedrijven.beheren'), 'corrigeren' => Gate::allows('tegoed.corrigeren')],
        ]);
    }

    public function gegevens(Request $request, Company $bedrijf): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'kvk' => ['nullable', 'string', 'max:20'],
            'branche' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'account_manager_id' => ['nullable', 'integer', Rule::in($this->teamleden()->pluck('id'))],
        ], ['account_manager_id.in' => 'Kies een teamlid als accountmanager.']);
        $this->beheer->gegevens($bedrijf, $data, $request->user());

        return back()->with('melding', 'Bedrijfsgegevens opgeslagen.');
    }

    public function instellingen(Request $request, Company $bedrijf): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        $data = $request->validate(['self_confirm_visits' => ['sometimes', 'boolean'], 'owner_approves_content' => ['sometimes', 'boolean']]);
        $this->beheer->instellingen($bedrijf, $data, $request->user());

        return back()->with('melding', 'Instelling opgeslagen.');
    }

    public function vestigingToevoegen(VestigingRequest $request, Company $bedrijf): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        $this->beheer->vestigingOpslaan($bedrijf, null, $request->validated(), $request->user());

        return back()->with('melding', 'Vestiging toegevoegd.');
    }

    public function vestigingBijwerken(VestigingRequest $request, Company $bedrijf, Location $vestiging): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        abort_unless($vestiging->company_id === $bedrijf->id, 404);
        $this->beheer->vestigingOpslaan($bedrijf, $vestiging, $request->validated(), $request->user());

        return back()->with('melding', 'Vestiging en bezoektijden opgeslagen.');
    }

    public function uitnodigen(UitnodigingRequest $request, Company $bedrijf): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        $data = $request->validated();
        $this->beheer->uitnodigen($bedrijf, $data['name'] ?? null, $data['email'], $data['role'], $request->user());

        return back()->with('melding', 'Uitnodiging verstuurd naar '.$data['email'].'.');
    }

    public function opnieuwUitnodigen(Request $request, Company $bedrijf, User $gebruiker): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        $this->lidVan($bedrijf, $gebruiker);
        $this->beheer->opnieuwUitnodigen($bedrijf, $gebruiker, $request->user());

        return back()->with('melding', 'Uitnodiging opnieuw verstuurd.');
    }

    public function rol(Request $request, Company $bedrijf, User $gebruiker): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        $this->lidVan($bedrijf, $gebruiker);
        $data = $request->validate(['role' => ['required', 'in:eigenaar,lid']]);
        $this->beheer->rolWijzigen($bedrijf, $gebruiker, $data['role'], $request->user());

        return back()->with('melding', 'Rol gewijzigd.');
    }

    public function verwijderen(Request $request, Company $bedrijf, User $gebruiker): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        $this->lidVan($bedrijf, $gebruiker);
        $this->beheer->verwijderen($bedrijf, $gebruiker, $request->user());

        return back()->with('melding', "{$gebruiker->name} heeft geen toegang meer tot {$bedrijf->name}.");
    }

    public function correctie(Request $request, Company $bedrijf): RedirectResponse
    {
        Gate::authorize('tegoed.corrigeren');
        $data = $request->validate([
            'points' => ['required', 'integer', 'not_in:0', 'between:-500,500'],
            'reason' => ['required', 'string', 'min:5', 'max:250'],
        ], ['points.not_in' => 'Een correctie van 0 punten verandert niets.'], ['points' => 'punten', 'reason' => 'reden']);
        $this->beheer->tegoedCorrigeren($bedrijf, (int) $data['points'], $data['reason'], $request->user());

        return back()->with('melding', 'Correctie geboekt in het grootboek.');
    }

    public function verzoekAfhandelen(Request $request, Company $bedrijf, Task $taak): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        abort_unless($taak->company_id === $bedrijf->id && $taak->type === 'abonnement', 404);
        $this->beheer->verzoekAfhandelen($taak, $request->user());

        return back()->with('melding', 'Verzoek afgehandeld.');
    }

    /** Teamleden die accountmanager kunnen zijn (iedereen met een teamrol). */
    private function teamleden()
    {
        return User::with('roles')->whereHas('roles')->orderBy('name')->get()->filter(fn (User $u) => $u->isTeam())->values();
    }

    private function lidVan(Company $bedrijf, User $gebruiker): void
    {
        abort_unless($bedrijf->users()->whereKey($gebruiker->id)->exists(), 404);
    }
}
