<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Creator;
use App\Models\CreditLedger;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\User;
use App\Services\Uitnodiging;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Basisbeheer voor de keten: bedrijven en creators aanmaken en uitnodigen, aanmeldingen van de website.
 * Fase 1 werkt dit verder uit (screening, vestigingen bewerken, rechtensjablonen).
 */
class BeheerController extends Controller
{
    public function __construct(private Uitnodiging $uitnodiging) {}

    public function bedrijven(): Response
    {
        Gate::authorize('bedrijven.bekijken');

        return Inertia::render('team/bedrijven', [
            'can' => ['beheren' => Gate::allows('bedrijven.beheren')],
            'companies' => Company::with(['locations', 'owners', 'subscriptions.planVersion.plan'])->orderBy('name')->get()->map(fn (Company $b) => [
                'id' => $b->id, 'name' => $b->name, 'branche' => $b->branche,
                'owner' => $b->owners->first()?->only(['id', 'name', 'email']),
                'locations' => $b->locations->pluck('name'),
                'plan' => $b->subscriptions->last()?->planVersion->plan->name,
                ...$b->tegoed(),
            ])->values(),
            'plans' => Plan::all()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'points' => $p->currentVersion()?->points_per_month, 'price_cents' => $p->currentVersion()?->price_cents])->values(),
            'leads' => Lead::where('type', Lead::TYPE_ONDERNEMER)->where('status', 'nieuw')->latest()->get(['id', 'name', 'email', 'phone', 'company', 'city']),
        ]);
    }

    public function bedrijfOpslaan(Request $request): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'branche' => ['nullable', 'string', 'max:60'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:190'],
            'location_name' => ['required', 'string', 'max:120'],
            'location_address' => ['nullable', 'string', 'max:190'],
            'location_city' => ['required', 'string', 'max:120'],
            'experience' => ['nullable', 'string', 'max:1000'],
            'plan_id' => ['required', 'exists:plans,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $bedrijf = Company::create(['name' => $data['name'], 'branche' => $data['branche'] ?? null, 'email' => $data['owner_email'], 'account_manager_id' => $request->user()->id]);
            $vestiging = $bedrijf->locations()->create(['name' => $data['location_name'], 'address' => $data['location_address'] ?? null, 'city' => $data['location_city'], 'experience' => $data['experience'] ?? null]);
            foreach (range(1, 5) as $dag) { // VOORSTEL: standaard ma t/m vr 10 - 16 uur, door het bedrijf aan te passen (fase 1)
                $vestiging->visitWindows()->create(['weekday' => $dag, 'starts_at' => '10:00', 'ends_at' => '16:00']);
            }

            $versie = Plan::findOrFail($data['plan_id'])->currentVersion();
            $bedrijf->subscriptions()->create(['plan_version_id' => $versie->id, 'starts_on' => today()]);
            $bedrijf->ledger()->create(['kind' => CreditLedger::TOEKENNEN, 'points' => $versie->points_per_month, 'reason' => 'Tegoed '.today()->translatedFormat('F Y'), 'user_id' => $request->user()->id]);

            $eigenaar = $this->account($data['owner_name'], $data['owner_email'], 'Ondernemer Eigenaar');
            $bedrijf->users()->syncWithoutDetaching([$eigenaar->id => ['role' => 'eigenaar']]);
            $this->uitnodiging->stuur($eigenaar, 'o-welkom', ['zaak' => $bedrijf->name, 'rol' => 'eigenaar']);

            if (! empty($data['lead_id'])) {
                Lead::whereKey($data['lead_id'])->update(['status' => 'klant']);
            }
        });

        return back()->with('melding', 'Bedrijf aangemaakt en de eigenaar is uitgenodigd.');
    }

    public function creators(): Response
    {
        Gate::authorize('creators.bekijken');

        return Inertia::render('team/creators', [
            'can' => ['beheren' => Gate::allows('creators.beheren')],
            'creators' => Creator::with('user')->get()->map(fn (Creator $c) => [
                'id' => $c->id, 'user_id' => $c->user_id, 'name' => $c->user->name, 'email' => $c->user->email, 'level' => $c->levelName(), 'city' => $c->city,
                'instagram' => $c->instagram, 'tiktok' => $c->tiktok, 'status' => $c->status, 'age' => $c->age(),
                'jobs' => $c->collaborations()->count(),
            ])->sortBy('name')->values(),
            'levels' => collect(config('mebranding.levels'))->map(fn ($l, $k) => ['value' => $k, 'name' => $l['name']])->values(),
            'leads' => Lead::where('type', Lead::TYPE_CREATOR)->where('status', 'nieuw')->latest()->get(['id', 'name', 'email', 'phone', 'city', 'data']),
        ]);
    }

    public function creatorOpslaan(Request $request): RedirectResponse
    {
        Gate::authorize('creators.beheren');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'level' => ['required', 'in:'.implode(',', array_keys(config('mebranding.levels')))],
            'birthdate' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'city' => ['nullable', 'string', 'max:120'],
            'instagram' => ['nullable', 'string', 'max:60'],
            'tiktok' => ['nullable', 'string', 'max:60'],
            'lead_id' => ['nullable', 'exists:leads,id'],
        ], ['birthdate.before_or_equal' => 'Creators zijn minimaal 18 jaar.']);

        DB::transaction(function () use ($data) {
            $user = $this->account($data['name'], $data['email'], 'Creator');
            Creator::updateOrCreate(['user_id' => $user->id], [
                'level' => $data['level'], 'birthdate' => $data['birthdate'], 'city' => $data['city'] ?? null,
                'instagram' => $data['instagram'] ?? null, 'tiktok' => $data['tiktok'] ?? null, 'status' => 'actief',
            ]);
            $this->uitnodiging->stuur($user, 'c-welkom', Uitnodiging::creatorVars($user->creator()->first()));
            if (! empty($data['lead_id'])) {
                Lead::whereKey($data['lead_id'])->update(['status' => 'creator']);
            }
        });

        return back()->with('melding', 'Creator aangemaakt en uitgenodigd.');
    }

    private function account(string $naam, string $email, string $rol): User
    {
        $user = User::firstOrCreate(['email' => Str::lower($email)], ['name' => $naam, 'password' => Str::password(40)]);
        $user->assignRole($rol);

        return $user;
    }
}
