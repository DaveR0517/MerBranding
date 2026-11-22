<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Services\Bedrijfsbeheer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Demodata voor module ondernemer-beheer: een lid bij Lunchroom Bloesem met een open uitnodiging. Na DemoSeeder, veilig om vaker te draaien. */
class DemoOndernemerBeheerSeeder extends Seeder
{
    public function run(): void
    {
        $bloesem = Company::where('name', 'Lunchroom Bloesem')->first();
        if (! $bloesem) {
            return;
        }

        $jorrit = User::firstOrCreate(['email' => 'jorrit@'.DemoSeeder::DOMEIN], ['name' => 'Jorrit Veenstra', 'password' => Str::password(40)]);
        $bloesem->users()->syncWithoutDetaching([$jorrit->id => ['role' => 'lid']]);
        Bedrijfsbeheer::syncRol($jorrit);
        Password::broker()->createToken($jorrit); // open uitnodiging, zonder mail
    }
}
