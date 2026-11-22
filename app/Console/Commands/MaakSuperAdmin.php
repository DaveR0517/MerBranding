<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

#[Signature('mebranding:super-admin {email} {naam}')]
#[Description('Maak of update een Super Admin-account en toon eenmalig een link om het wachtwoord in te stellen')]
class MaakSuperAdmin extends Command
{
    public function handle(): int
    {
        if (! Role::where('name', 'Super Admin')->exists()) {
            $this->call('db:seed', ['--class' => RolesSeeder::class, '--force' => true]);
        }

        $user = User::firstOrCreate(
            ['email' => Str::lower($this->argument('email'))],
            ['name' => $this->argument('naam'), 'password' => Str::password(40)],
        );
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
        $user->assignRole('Super Admin');

        $token = Password::broker()->createToken($user);
        $url = 'https://'.config('mebranding.domains.app').'/reset-password/'.$token.'?email='.urlencode($user->email);

        $this->info("Super Admin klaar: {$user->email}");
        $this->line('Stel je wachtwoord in via deze link (eenmalig, 60 minuten geldig):');
        $this->line($url);

        return self::SUCCESS;
    }
}
