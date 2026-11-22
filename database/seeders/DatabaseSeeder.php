<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Basisgegevens, ook in productie. Demodata komt later in DemoSeeder (nooit in productie).
     */
    public function run(): void
    {
        $this->call(RolesSeeder::class);
    }
}
