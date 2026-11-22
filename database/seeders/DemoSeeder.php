<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Creator;
use App\Models\CreditLedger;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demodata uit het voorbeeld (data/keten.json): geen echte mensen. Alleen voor tests en de testomgeving,
 * nooit op het echte platform. Wachtwoorden zijn willekeurig: een Super Admin gebruikt "Inloggen als".
 */
class DemoSeeder extends Seeder
{
    public const DOMEIN = 'demo.mebranding.nl';

    public function run(): void
    {
        $this->call([RolesSeeder::class, MessageTemplateSeeder::class, PlanSeeder::class]);

        $eva = $this->gebruiker('Eva Bakker', 'eva', 'Accountmanager');
        $this->gebruiker('Ruben Visser', 'ruben', 'Contentcontrole');
        $this->gebruiker('Mirthe de Jong', 'mirthe', 'Sales');
        $sanne = $this->gebruiker('Sanne Hiemstra', 'sanne', 'Ondernemer Eigenaar');

        $bloesem = Company::firstOrCreate(['name' => 'Lunchroom Bloesem'], [
            'branche' => 'Horeca', 'email' => 'sanne@'.self::DOMEIN, 'account_manager_id' => $eva->id,
        ]);
        $bloesem->users()->syncWithoutDetaching([$sanne->id => ['role' => 'eigenaar']]);

        foreach (['Leeuwarden' => 'Nieuwestad 12', 'Sneek' => 'Marktstraat 4', 'Franeker' => 'Voorstraat 30'] as $plaats => $adres) {
            $vestiging = $bloesem->locations()->firstOrCreate(['name' => $plaats], [
                'address' => $adres, 'city' => $plaats, 'experience' => 'Lunch voor twee met een drankje naar keuze.',
            ]);
            if ($vestiging->visitWindows()->doesntExist()) {
                foreach (range(1, 7) as $dag) {
                    $vestiging->visitWindows()->create(['weekday' => $dag, 'starts_at' => '10:00', 'ends_at' => '16:00']);
                }
            }
        }

        if ($bloesem->subscriptions()->doesntExist()) {
            $groei = Plan::where('name', 'Groei')->first()->currentVersion();
            $bloesem->subscriptions()->create(['plan_version_id' => $groei->id, 'starts_on' => today()->startOfMonth()]);
            $bloesem->ledger()->create(['kind' => CreditLedger::TOEKENNEN, 'points' => $groei->points_per_month, 'reason' => 'Tegoed '.today()->translatedFormat('F Y').' (Groei)']);
        }

        $this->creator('Sophie de Vries', 'sophie', 'elite', '1994-03-12', 'Leeuwarden', '@sophieeet');
        $this->creator('Marit Postma', 'marit', 'bronze', '2004-07-02', 'Sneek', '@maritmaakt');
    }

    private function gebruiker(string $naam, string $login, string $rol): User
    {
        $user = User::firstOrCreate(['email' => "{$login}@".self::DOMEIN], [
            'name' => $naam, 'password' => Str::password(40), 'email_verified_at' => now(),
        ]);
        $user->assignRole($rol);

        return $user;
    }

    private function creator(string $naam, string $login, string $niveau, string $geboren, string $plaats, string $instagram): Creator
    {
        $user = $this->gebruiker($naam, $login, 'Creator');

        return Creator::firstOrCreate(['user_id' => $user->id], [
            'level' => $niveau, 'kind' => 'beide', 'niches' => ['Eten en drinken'], 'birthdate' => $geboren,
            'city' => $plaats, 'instagram' => $instagram, 'status' => 'actief',
        ]);
    }
}
