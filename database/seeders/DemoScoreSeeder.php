<?php

namespace Database\Seeders;

use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Creator;
use App\Models\Publication;
use App\Models\Status;
use App\Models\User;
use App\Services\Score;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Demodata voor score en klassement (alleen demo en tests, nooit productie). Los aan te roepen na DemoSeeder:
 * php artisan db:seed --class=DemoScoreSeeder. Idempotent: bestaande demosamenwerkingen (nummer DEMO-S-...) worden overgeslagen.
 * Personen zijn verzonnen voorbeeldcreators.
 */
class DemoScoreSeeder extends Seeder
{
    /** naam, login, niveau, soort, plaats, niches, dagen geleden aangemeld, samenwerkingen [dagen geleden, sterren, views, interactie], eerdere samenwerkingen (buiten de 90 dagen) */
    private const CREATORS = [
        ['Lotte Hoekstra', 'lotte', 'platinum', 'influencer', 'Groningen', ['Eten en drinken', 'Uitjes'], 400, [[80, 5, 38000, 3400], [62, 5, 41000, 3900], [44, 4, 29000, 2500], [20, 5, 47000, 4300], [6, 5, 52000, 5100]], 8],
        ['Jesse Wierda', 'jesse', 'gold', 'beide', 'Leeuwarden', ['Eten en drinken'], 300, [[70, 4, 21000, 1500], [50, 4, 18000, 1300], [30, 5, 26000, 2100], [9, 4, 24000, 1900]], 4],
        ['Noor Dijkstra', 'noor', 'gold', 'influencer', 'Sneek', ['Overnachten', 'Uitjes'], 280, [[85, 2, 3000, 90], [60, 3, 2500, 80], [15, 2, 3000, 100]], 4],
        ['Daan Bosma', 'daan', 'silver', 'ugc', 'Assen', ['Eten en drinken', 'Winkels'], 220, [[60, 5, 15000, 1400], [33, 5, 17000, 1600], [12, 5, 19000, 1800]], 0],
        ['Femke Wijbenga', 'femke', 'bronze', 'ugc', 'Drachten', ['Uitjes'], 48, [[10, 4, 6000, 450]], 0],
        ['Iris Kuipers', 'iris', 'bronze', 'ugc', 'Heerenveen', ['Eten en drinken'], 59, [[50, 4, 6000, 450], [35, 3, 4000, 220], [8, 4, 5200, 380]], 0],
    ];

    public function run(): void
    {
        $bedrijf = Company::with('locations')->first();
        if (! $bedrijf) {
            $this->command?->warn('Draai eerst DemoSeeder.');

            return;
        }

        foreach (self::CREATORS as [$naam, $login, $niveau, $soort, $plaats, $niches, $sinds, $klussen, $eerder]) {
            $user = User::firstOrCreate(['email' => "{$login}@demo.mebranding.nl"], ['name' => $naam, 'password' => Str::password(40), 'email_verified_at' => now()]);
            $user->assignRole('Creator');
            $creator = Creator::firstOrCreate(['user_id' => $user->id], [
                'level' => $niveau, 'kind' => $soort, 'niches' => $niches, 'city' => $plaats, 'status' => 'actief', 'birthdate' => '1998-05-01',
            ]);
            $creator->forceFill(['created_at' => now()->subDays($sinds)])->save();

            foreach ($klussen as $i => [$dagen, $sterren, $views, $interactie]) {
                $this->klus($bedrijf, $creator, "DEMO-S-{$login}-{$i}", $dagen, $sterren, $views, $interactie);
            }
            $this->eerder($bedrijf, $creator, $login, $eerder);
        }

        // Sophie (Elite) met een sterke reeks, zodat de ranglijst een duidelijke nummer 1 heeft.
        if ($sophie = User::where('email', 'sophie@demo.mebranding.nl')->first()?->creator) {
            $sophie->forceFill(['created_at' => now()->subYears(2)])->save();
            foreach ([[70, 5, 61000, 6200], [41, 5, 58000, 6000], [5, 5, 64000, 6900]] as $i => [$dagen, $sterren, $views, $interactie]) {
                $this->klus($bedrijf, $sophie, "DEMO-S-sophie-{$i}", $dagen, $sterren, $views, $interactie);
            }
            $this->eerder($bedrijf, $sophie, 'sophie', 14);
        }

        // Verloop: elke week een meting over de afgelopen 12 weken, daarna vandaag en de niveaumeting (alleen voorstellen).
        $score = app(Score::class);
        foreach (range(12, 1) as $week) {
            $score->herberekenAlles(now()->subWeeks($week));
        }
        $score->herberekenAlles(now()->startOfMonth()->subMonthNoOverflow()); // vorige niveaumeting
        $score->herberekenAlles();
        $score->niveaumeting();
    }

    /** Oudere afgeronde samenwerkingen, zodat het totale aantal past bij het niveau. */
    private function eerder(Company $bedrijf, Creator $creator, string $login, int $aantal): void
    {
        for ($i = 1; $i <= $aantal; $i++) {
            $this->klus($bedrijf, $creator, "DEMO-S-{$login}-e{$i}", 200 + $i * 15, 4, 20000, 1500);
        }
    }

    private function klus(Company $bedrijf, Creator $creator, string $nummer, int $dagen, int $sterren, int $views, int $interactie): void
    {
        if (Collaboration::where('number', $nummer)->exists()) {
            return;
        }

        $af = now()->subDays($dagen)->setTime(12, 0);
        $c = Collaboration::create([
            'number' => $nummer, 'company_id' => $bedrijf->id, 'location_id' => $bedrijf->locations->random()->id, 'creator_id' => $creator->id,
            'title' => 'Demo samenwerking', 'briefing' => 'Voorbeeld voor het klassement.', 'status' => Status::Afgerond, 'points' => $creator->points(),
            'creator_level' => $creator->level, 'visit_at' => $af->copy()->subDays(20), 'visited_at' => $af->copy()->subDays(20),
            'publish_on' => $af->copy()->subDays(14)->toDateString(), 'live_at' => $af->copy()->subDays(14), 'measure_until' => $af, 'completed_at' => $af,
        ]);
        $c->review()->create(['user_id' => $bedrijf->users()->first()?->id ?? User::first()->id, 'rating' => $sterren]);

        $publicatie = Publication::create(['collaboration_id' => $c->id, 'platform' => 'instagram', 'url' => "https://www.instagram.com/p/{$nummer}/", 'posted_at' => $c->live_at]);
        if (Schema::hasTable('stat_snapshots')) {
            $kolommen = Schema::getColumnListing('stat_snapshots');
            foreach (['views' => $views, 'likes' => (int) ($interactie * 0.8), 'comments' => (int) ($interactie * 0.1), 'shares' => (int) ($interactie * 0.1)] as $metriek => $waarde) {
                DB::table('stat_snapshots')->insert(array_intersect_key([
                    'publication_id' => $publicatie->id, 'metric' => $metriek, 'value' => $waarde, 'source' => 'instagram', 'period' => 'totaal',
                    'fetched_at' => $af, 'is_estimate' => false, 'created_at' => $af, 'updated_at' => $af,
                ], array_flip($kolommen)));
            }
        }
    }
}
