<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

#[Signature('mebranding:accounts {--wachtwoord-bijwerken : Zet ook bij bestaande accounts het wachtwoord uit de .env}')]
#[Description('Maak de vaste accounts uit de .env (DAIVE_ACCOUNT_n_*) aan. Bestaande wachtwoorden blijven staan, tenzij --wachtwoord-bijwerken.')]
class SyncAccounts extends Command
{
    /** Rol in de .env naar rol in het platform. */
    private const ROLLEN = ['eigenaar' => 'Super Admin', 'super admin' => 'Super Admin', 'medewerker' => 'Medewerker'];

    public function handle(): int
    {
        if (! Role::where('name', 'Super Admin')->exists()) {
            $this->call('db:seed', ['--class' => RolesSeeder::class, '--force' => true]);
        }

        foreach (config('mebranding.accounts') as $a) {
            $rol = self::ROLLEN[Str::lower($a['rol'])] ?? null;
            if (! $rol) {
                $this->warn("Onbekende rol \"{$a['rol']}\" voor {$a['email']}: overgeslagen.");

                continue;
            }

            $user = User::firstOrNew(['email' => Str::lower($a['email'])]);
            $nieuw = ! $user->exists;
            $user->name = $a['naam'] ?: Str::before($a['email'], '@');
            if ($nieuw || $this->option('wachtwoord-bijwerken')) {
                $user->password = Hash::make($a['wachtwoord']);
            }
            $user->email_verified_at ??= now();
            $user->save();
            $user->assignRole($rol);

            $this->info(($nieuw ? 'Aangemaakt' : 'Bijgewerkt').": {$user->email} ({$rol})");
        }

        return self::SUCCESS;
    }
}
