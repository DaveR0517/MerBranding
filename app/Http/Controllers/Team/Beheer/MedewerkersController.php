<?php

namespace App\Http\Controllers\Team\Beheer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Melder;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Team en rechten (CLAUDE.md 5 en 8): medewerkers uitnodigen, rechtensjablonen wijzigen, deactiveren.
 * Deactiveren haalt de teamrollen weg en verwijdert niets. Elke rechtenwijziging staat in het logboek
 * en de betrokkene krijgt melding t-rechten. Alleen een Super Admin maakt of wijzigt een Super Admin.
 */
class MedewerkersController extends Controller
{
    public const SUPER_ADMIN = 'Super Admin';

    public function __construct(private Melder $melder) {}

    public function index(Request $request): Response
    {
        Gate::authorize('team.beheren');

        $teamRollen = $this->teamRollen();
        $leden = User::with('roles')->whereHas('roles', fn ($q) => $q->whereIn('name', $teamRollen))->orderBy('name')->get();

        $gedeactiveerdIds = ActivityLog::where('event', 'medewerker_gedeactiveerd')->where('subject_type', (new User)->getMorphClass())->pluck('subject_id')->unique();
        $gedeactiveerd = User::with('roles')->whereIn('id', $gedeactiveerdIds)->whereNotIn('id', $leden->pluck('id'))->orderBy('name')->get();

        $sjablonen = Role::with('permissions')->whereIn('name', array_keys(RolesSeeder::TEMPLATES))->get()
            ->sortBy(fn (Role $r) => array_search($r->name, array_keys(RolesSeeder::TEMPLATES), true))
            ->map(fn (Role $r) => [
                'name' => $r->name,
                'rechten' => $r->permissions->pluck('name')->values(),
                'aantal' => $leden->filter(fn (User $u) => $u->roles->contains('name', $r->name))->count(),
            ])->values();

        return Inertia::render('team/beheer/team', [
            'medewerkers' => $leden->map(fn (User $u) => $this->rij($u, $request->user()))->values(),
            'gedeactiveerd' => $gedeactiveerd->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values(),
            'sjablonen' => $sjablonen,
            'rechten' => collect(RolesSeeder::PERMISSIONS)->map(fn ($p) => ['name' => $p, 'label' => RolesSeeder::LABELS[$p] ?? $p])->values(),
            'kan_super_admin' => $request->user()->hasRole(self::SUPER_ADMIN),
            'tweestaps_verplicht' => (bool) config('mebranding.team_2fa_verplicht'),
        ]);
    }

    /** Voor elk teamlid: je eigen rollen en wat je daarmee mag (de link in melding t-rechten). */
    public function mijnRechten(Request $request): Response
    {
        $user = $request->user();
        $rollen = $this->huidig($user);

        return Inertia::render('team/beheer/mijn-rechten', [
            'rollen' => $rollen,
            'rechten' => collect($user->hasRole(self::SUPER_ADMIN) ? RolesSeeder::PERMISSIONS : $user->getAllPermissions()->pluck('name'))
                ->map(fn ($p) => RolesSeeder::LABELS[$p] ?? $p)->sort()->values(),
            'tweestaps' => (bool) $user->two_factor_confirmed_at,
        ]);
    }

    public function uitnodigen(Request $request): RedirectResponse
    {
        Gate::authorize('team.beheren');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'rollen' => ['required', 'array', 'min:1'],
            'rollen.*' => ['string', Rule::in($this->kiesbaar())],
        ], ['rollen.required' => 'Kies minimaal één rechtensjabloon.', 'rollen.min' => 'Kies minimaal één rechtensjabloon.']);
        $this->magSuperAdmin($request->user(), $data['rollen']);

        $email = Str::lower($data['email']);
        $bestaand = User::with('roles')->where('email', $email)->first();
        if ($bestaand?->roles->whereIn('name', $this->teamRollen())->isNotEmpty()) {
            throw ValidationException::withMessages(['email' => 'Deze persoon zit al in het team. Wijzig de rollen in de lijst.']);
        }

        DB::transaction(function () use ($data, $email, $bestaand, $request) {
            $user = $bestaand ?? User::create(['name' => $data['name'], 'email' => $email, 'password' => Str::password(40)]);
            $user->assignRole($data['rollen']);
            $this->log($request->user(), $user, 'medewerker_uitgenodigd', [], $data['rollen']);

            if ($bestaand) {
                $this->meld($user, $request->user(), $data['rollen']);
            } else {
                // Uitnodiging per mail: de link laat de nieuwe medewerker zelf een wachtwoord kiezen.
                $token = Password::broker()->createToken($user);
                $this->melder->stuur('g-uitnodiging', $user, ['naam' => $request->user()->name, 'zaak' => 'MEBRANDING', 'rol' => implode(', ', $data['rollen'])],
                    route('password.reset', ['token' => $token, 'email' => $user->email]));
            }
        });

        return back()->with('melding', "{$data['name']} is uitgenodigd.");
    }

    public function rollen(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('team.beheren');
        $data = $request->validate([
            'rollen' => ['required', 'array', 'min:1'],
            'rollen.*' => ['string', Rule::in($this->kiesbaar())],
        ], ['rollen.required' => 'Kies minimaal één rechtensjabloon. Wil je iemand uit het team halen? Kies Deactiveren.', 'rollen.min' => 'Kies minimaal één rechtensjabloon.']);

        $this->magWijzigen($request->user(), $user);
        $oud = $this->huidig($user);
        $nieuw = array_values(array_unique($data['rollen']));
        $this->magSuperAdmin($request->user(), array_diff(array_merge($oud, $nieuw), array_intersect($oud, $nieuw)));

        if (! in_array(self::SUPER_ADMIN, $nieuw, true)) {
            $this->nietDeLaatsteSuperAdmin($user);
        }

        if (collect($oud)->sort()->values()->all() === collect($nieuw)->sort()->values()->all()) {
            return back()->with('melding', 'Er is niets veranderd.');
        }

        $this->zet($user, $nieuw);
        $this->log($request->user(), $user, 'rechten_gewijzigd', $oud, $nieuw);
        $this->meld($user, $request->user(), $nieuw);

        return back()->with('melding', "Rollen van {$user->name} gewijzigd.");
    }

    public function deactiveren(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('team.beheren');
        $this->magWijzigen($request->user(), $user);
        $oud = $this->huidig($user);
        $this->magSuperAdmin($request->user(), $oud);
        $this->nietDeLaatsteSuperAdmin($user);

        $this->zet($user, []);
        $this->log($request->user(), $user, 'medewerker_gedeactiveerd', $oud, []);
        $this->meld($user, $request->user(), []);

        return back()->with('melding', "{$user->name} is gedeactiveerd. Het account en alle gegevens blijven bewaard.");
    }

    /** Alle rollen die bij het team horen. Rollen buiten het team (ondernemer, creator, student) blijven altijd staan. */
    private function teamRollen(): array
    {
        return [...array_keys(RolesSeeder::TEMPLATES), self::SUPER_ADMIN, 'Medewerker'];
    }

    /** Wat je in het formulier kunt kiezen. Super Admin staat erbij, maar alleen een Super Admin mag hem geven. */
    private function kiesbaar(): array
    {
        return [...array_keys(RolesSeeder::TEMPLATES), self::SUPER_ADMIN];
    }

    private function huidig(User $user): array
    {
        return $user->roles()->whereIn('name', $this->teamRollen())->pluck('name')->values()->all();
    }

    /** Teamrollen vervangen, andere rollen (bijvoorbeeld Creator) houden. */
    private function zet(User $user, array $teamrollen): void
    {
        $anders = $user->roles()->whereNotIn('name', $this->teamRollen())->pluck('name')->all();
        $user->syncRoles([...$anders, ...$teamrollen]);
    }

    private function magSuperAdmin(User $door, array $rollen): void
    {
        abort_if(in_array(self::SUPER_ADMIN, $rollen, true) && ! $door->hasRole(self::SUPER_ADMIN), 403, 'Alleen een Super Admin kan iemand Super Admin maken of die rol weghalen.');
    }

    private function magWijzigen(User $door, User $user): void
    {
        if ($door->is($user)) {
            throw ValidationException::withMessages(['rollen' => 'Je eigen rollen wijzigt een andere beheerder.']);
        }
        abort_if($user->hasRole(self::SUPER_ADMIN) && ! $door->hasRole(self::SUPER_ADMIN), 403, 'Alleen een Super Admin wijzigt een Super Admin.');
    }

    private function nietDeLaatsteSuperAdmin(User $user): void
    {
        if ($user->hasRole(self::SUPER_ADMIN) && User::role(self::SUPER_ADMIN)->count() <= 1) {
            throw ValidationException::withMessages(['rollen' => 'Er moet minstens één Super Admin overblijven.']);
        }
    }

    private function log(User $door, User $user, string $event, array $oud, array $nieuw): void
    {
        ActivityLog::create([
            'user_id' => $door->id,
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
            'event' => $event,
            'reason' => Str::limit("{$user->name}: ".($nieuw ? implode(', ', $nieuw) : 'geen teamrollen meer'), 250),
            'data' => ['oud' => $oud, 'nieuw' => $nieuw],
        ]);
    }

    private function meld(User $user, User $door, array $rollen): void
    {
        $this->melder->stuur('t-rechten', $user, ['wie' => $door->name, 'rollen' => $rollen ? implode(', ', $rollen) : 'geen (je hebt geen toegang meer tot het teamportaal)'], route('team.beheer.mijn-rechten'));
    }

    /** @return array<string, mixed> */
    private function rij(User $u, User $ik): array
    {
        $rollen = $u->roles->pluck('name')->intersect($this->teamRollen())->values();

        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'rollen' => $rollen,
            'tweestaps' => (bool) $u->two_factor_confirmed_at,
            'email_bevestigd' => $u->email_verified_at !== null,
            'is_super_admin' => $rollen->contains(self::SUPER_ADMIN),
            'is_ik' => $u->is($ik),
        ];
    }
}
