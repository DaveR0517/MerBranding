<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyOnboarding;
use App\Models\CreditLedger;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Beheer van een bedrijf door de ondernemer of het team: vestigingen en bezoektijden, instellingen,
 * gebruikers, verzoeken over het abonnement en correcties op het tegoed. Alles komt in het logboek.
 */
class Bedrijfsbeheer
{
    public const ROLLEN = ['eigenaar' => 'Ondernemer Eigenaar', 'lid' => 'Ondernemer Lid'];

    public function __construct(private Uitnodiging $uitnodiging) {}

    /**
     * Vestiging aanmaken of bijwerken, met de bezoektijden per weekdag (de oude tijden worden vervangen).
     *
     * @param  array{name: string, address?: ?string, city?: ?string, experience?: ?string, windows?: list<array{weekday: int, starts_at: string, ends_at: string}>}  $data
     */
    public function vestigingOpslaan(Company $bedrijf, ?Location $vestiging, array $data, User $door): Location
    {
        return DB::transaction(function () use ($bedrijf, $vestiging, $data, $door) {
            $velden = ['name' => $data['name'], 'address' => $data['address'] ?? null, 'city' => $data['city'] ?? null, 'experience' => $data['experience'] ?? null];
            $nieuw = $vestiging === null;
            $vestiging = $nieuw ? $bedrijf->locations()->create($velden) : tap($vestiging)->update($velden);

            $vestiging->visitWindows()->delete();
            foreach ($data['windows'] ?? [] as $w) {
                $vestiging->visitWindows()->create(['weekday' => (int) $w['weekday'], 'starts_at' => $w['starts_at'], 'ends_at' => $w['ends_at']]);
            }

            $this->log($door, $bedrijf, $nieuw ? 'vestiging_toegevoegd' : 'vestiging_gewijzigd', null, ['location_id' => $vestiging->id, 'naam' => $vestiging->name, 'tijden' => count($data['windows'] ?? [])]);
            CompanyOnboarding::vink($bedrijf, 'vestiging');

            return $vestiging;
        });
    }

    /** @param  array{self_confirm_visits?: bool, owner_approves_content?: bool}  $data */
    public function instellingen(Company $bedrijf, array $data, User $door): void
    {
        $bedrijf->fill(array_intersect_key($data, array_flip(['self_confirm_visits', 'owner_approves_content'])));
        if ($bedrijf->isDirty()) {
            $wijzigingen = $bedrijf->getDirty();
            $bedrijf->save();
            $this->log($door, $bedrijf, 'bedrijfsinstellingen', null, $wijzigingen);
        }
    }

    /** Bedrijfsgegevens en accountmanager (alleen het team). */
    public function gegevens(Company $bedrijf, array $data, User $door): void
    {
        $bedrijf->fill($data);
        if ($bedrijf->isDirty()) {
            $wijzigingen = $bedrijf->getDirty();
            $bedrijf->save();
            $this->log($door, $bedrijf, 'bedrijf_gewijzigd', null, $wijzigingen);
        }
    }

    /** Nieuwe gebruiker voor het bedrijf: account (of bestaand account), koppeling en een mail met een link om een wachtwoord te kiezen. */
    public function uitnodigen(Company $bedrijf, ?string $naam, string $email, string $rol, User $door): User
    {
        $email = Str::lower(trim($email));
        if ($bedrijf->users()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Deze persoon heeft al toegang tot '.$bedrijf->name.'.']);
        }

        return DB::transaction(function () use ($bedrijf, $naam, $email, $rol, $door) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $naam ?: Str::before($email, '@'), 'password' => Str::password(40)]);
            $bedrijf->users()->attach($user->id, ['role' => $rol]);
            self::syncRol($user);
            $this->stuurUitnodiging($bedrijf, $user, $rol, $door);
            $this->log($door, $bedrijf, 'gebruiker_uitgenodigd', null, ['user_id' => $user->id, 'email' => $email, 'rol' => $rol]);
            CompanyOnboarding::vink($bedrijf, 'team');

            return $user;
        });
    }

    public function opnieuwUitnodigen(Company $bedrijf, User $user, User $door): void
    {
        $rol = $this->rolVan($bedrijf, $user);
        $this->stuurUitnodiging($bedrijf, $user, $rol, $door);
        $this->log($door, $bedrijf, 'uitnodiging_opnieuw', null, ['user_id' => $user->id, 'email' => $user->email]);
    }

    public function rolWijzigen(Company $bedrijf, User $user, string $rol, User $door): void
    {
        $oud = $this->rolVan($bedrijf, $user);
        if ($oud === $rol) {
            return;
        }
        if ($oud === 'eigenaar') {
            $this->nietLaatsteEigenaar($bedrijf, 'Er is altijd minstens één eigenaar. Maak eerst iemand anders eigenaar.');
        }

        $bedrijf->users()->updateExistingPivot($user->id, ['role' => $rol]);
        self::syncRol($user);
        $this->log($door, $bedrijf, 'gebruiker_rol', null, ['user_id' => $user->id, 'van' => $oud, 'naar' => $rol]);
    }

    /** Haalt de toegang tot dit bedrijf weg. Het account zelf blijft bestaan (één account per persoon). */
    public function verwijderen(Company $bedrijf, User $user, User $door): void
    {
        if ($this->rolVan($bedrijf, $user) === 'eigenaar') {
            $this->nietLaatsteEigenaar($bedrijf, 'Je kunt de laatste eigenaar niet verwijderen.');
        }

        $bedrijf->users()->detach($user->id);
        self::syncRol($user);
        $this->log($door, $bedrijf, 'gebruiker_verwijderd', null, ['user_id' => $user->id, 'email' => $user->email]);
    }

    /**
     * Verzoek van de ondernemer om het pakket te wijzigen of op te zeggen. Er wordt niets betaald of gewijzigd:
     * er komt een taak in de werkvoorraad van het team en een regel in het logboek.
     */
    public function abonnementVerzoek(Company $bedrijf, User $door, string $soort, ?Plan $plan, ?string $reden, ?string $toelichting): Task
    {
        $titel = $soort === 'opzeggen'
            ? "Opzegverzoek van {$bedrijf->name}"
            : "Verzoek pakketwijziging {$bedrijf->name}: naar {$plan?->name}";

        $taak = Task::create([
            'type' => 'abonnement', 'title' => Str::limit($titel, 250), 'company_id' => $bedrijf->id,
            'permission' => 'bedrijven.beheren', 'owner_id' => $bedrijf->account_manager_id, 'due_at' => $this->volgendeWerkdag(now()),
        ]);

        $this->log($door, $bedrijf, 'abonnement_verzoek', $reden ?? $toelichting, array_filter([
            'soort' => $soort, 'plan' => $plan?->name, 'reden' => $reden, 'toelichting' => $toelichting, 'task_id' => $taak->id,
        ]));

        return $taak;
    }

    public function verzoekAfhandelen(Task $taak, User $door): void
    {
        $taak->update(['closed_at' => now()]);
        $this->log($door, $taak->company, 'abonnement_verzoek_afgehandeld', null, ['task_id' => $taak->id, 'titel' => $taak->title]);
    }

    /** Handmatige correctie op het tegoed door het team: altijd met reden, als boeking in het grootboek en in het logboek. */
    public function tegoedCorrigeren(Company $bedrijf, int $punten, string $reden, User $door): CreditLedger
    {
        return DB::transaction(function () use ($bedrijf, $punten, $reden, $door) {
            $boeking = $bedrijf->ledger()->create(['kind' => CreditLedger::CORRECTIE, 'points' => $punten, 'reason' => $reden, 'user_id' => $door->id]);
            $this->log($door, $bedrijf, 'tegoed_correctie', $reden, ['punten' => $punten, 'ledger_id' => $boeking->id, 'saldo' => $bedrijf->availablePoints()]);

            return $boeking;
        });
    }

    public function rolVan(Company $bedrijf, User $user): ?string
    {
        return $bedrijf->users()->whereKey($user->id)->first()?->pivot->role;
    }

    /** De Spatie-rol volgt de koppelingen: eigenaar bij minstens één bedrijf, anders lid, zonder bedrijf geen van beide. */
    public static function syncRol(User $user): void
    {
        $rollen = $user->companies()->pluck('company_user.role');
        $doel = match (true) {
            $rollen->contains('eigenaar') => 'eigenaar',
            $rollen->contains('lid') => 'lid',
            default => null,
        };

        foreach (self::ROLLEN as $sleutel => $rol) {
            if ($sleutel === $doel) {
                $user->assignRole($rol);
            } elseif ($user->hasRole($rol)) {
                $user->removeRole($rol);
            }
        }
    }

    private function stuurUitnodiging(Company $bedrijf, User $user, string $rol, User $door): void
    {
        $this->uitnodiging->stuur($user, 'g-uitnodiging', ['naam' => $door->name, 'zaak' => $bedrijf->name, 'rol' => $rol]);
    }

    private function nietLaatsteEigenaar(Company $bedrijf, string $melding): void
    {
        if ($bedrijf->owners()->count() <= 1) {
            throw ValidationException::withMessages(['rol' => $melding]);
        }
    }

    private function volgendeWerkdag(CarbonInterface $vanaf): CarbonInterface
    {
        $dag = $vanaf->copy()->addDay();
        while ($dag->isWeekend()) {
            $dag = $dag->addDay();
        }

        return $dag->setTime(17, 0);
    }

    private function log(User $door, Company $bedrijf, string $event, ?string $reden, array $data): void
    {
        ActivityLog::create(['user_id' => $door->id, 'subject_type' => $bedrijf->getMorphClass(), 'subject_id' => $bedrijf->id, 'event' => $event, 'reason' => $reden ? Str::limit($reden, 250) : null, 'data' => $data]);
    }
}
