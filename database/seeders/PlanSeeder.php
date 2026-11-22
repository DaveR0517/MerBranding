<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/** Pakketten met prijsversies (CLAUDE.md 7.2). Alles VOORSTEL. */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Lokaal', 34900, 8], ['Groei', 79500, 20], ['Regio', 169500, 48]] as [$naam, $prijs, $punten]) {
            $plan = Plan::firstOrCreate(['name' => $naam]);
            $plan->versions()->firstOrCreate(['valid_from' => '2026-01-01'], ['price_cents' => $prijs, 'points_per_month' => $punten, 'is_proposal' => true]);
        }
    }
}
