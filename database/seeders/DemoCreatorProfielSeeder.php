<?php

namespace Database\Seeders;

use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Creator;
use App\Models\CreatorProfiel;
use App\Models\Lead;
use App\Models\Payout;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demodata voor de module creator-profiel: aanmeldingen om te screenen, een agenda en vergoedingen.
 * Los aan te roepen na DemoSeeder en veilig om vaker te draaien. Geen echte mensen.
 */
class DemoCreatorProfielSeeder extends Seeder
{
    public function run(): void
    {
        $aanmeldingen = [
            ['Marit Hylkema', 'marit.hylkema', 'Franeker', ['soort' => 'beide', 'straal' => '40', 'geboortedatum' => '2003-04-18', 'tiktok-naam' => '@marit.ontdekt', 'tiktok-volgers' => '2870', 'tiktok-zakelijk' => '1', 'instagram-naam' => '@marit.ontdekt', 'instagram-volgers' => '1240', 'niches' => ['eten', 'uitjes', 'reizen'], 'certificaat' => 'MB-ACA-2026-000027']],
            ['Jesse Bouma', 'jesse.bouma', 'Groningen', ['soort' => 'influencer', 'straal' => '25', 'geboortedatum' => '1999-11-02', 'instagram-naam' => '@jessebouma', 'instagram-volgers' => '8400', 'instagram-zakelijk' => '1', 'niches' => ['lifestyle', 'mode']]],
            ['Noor Veenstra', 'noor.veenstra', 'Assen', ['soort' => 'ugc', 'straal' => '60', 'geboortedatum' => '2001-02-14', 'tiktok-naam' => '@noorfilmt', 'tiktok-volgers' => '650', 'niches' => ['gezin', 'eten']]],
        ];
        foreach ($aanmeldingen as $i => [$naam, $login, $plaats, $data]) {
            Lead::firstOrCreate(['email' => "{$login}@".DemoSeeder::DOMEIN], [
                'type' => Lead::TYPE_CREATOR, 'name' => $naam, 'phone' => '06'.str_pad((string) (12345670 + $i), 8, '0', STR_PAD_LEFT),
                'city' => $plaats, 'data' => $data, 'consent_at' => now()->subDays(3 - $i), 'consent_version' => '0.1-concept',
            ]);
        }

        $marit = User::where('email', 'marit@'.DemoSeeder::DOMEIN)->first()?->creator;
        $sophie = User::where('email', 'sophie@'.DemoSeeder::DOMEIN)->first()?->creator;
        $bedrijf = Company::first();
        if (! $marit || ! $sophie || ! $bedrijf) {
            return;
        }

        CreatorProfiel::van($marit)->fill([
            'bio' => 'Ik film het liefst eten, drinken en uitjes rond Sneek.', 'radius_km' => 40,
            'portfolio_links' => ['https://voorbeeld.nl/marit'],
        ])->save();

        $vestigingen = $bedrijf->locations()->get();
        $this->klus($marit, $bedrijf->id, $vestigingen[0]->id, 'MB-DEMO-P01', 'Kerstlunch', Status::BezoekGepland, [
            'visit_at' => now()->addDays(1)->setTime(14, 0), 'content_deadline_at' => now()->addDays(6)->setTime(17, 0),
        ]);
        $this->klus($marit, $bedrijf->id, $vestigingen[1]->id, 'MB-DEMO-P02', 'Winterontbijt', Status::Goedgekeurd, [
            'visit_at' => now()->subDays(4)->setTime(10, 0), 'publish_on' => now()->addDays(3)->toDateString(),
        ]);
        $live = $this->klus($sophie, $bedrijf->id, $vestigingen[2]->id, 'MB-DEMO-P03', 'Proeverij', Status::Live, [
            'visit_at' => now()->subDays(9)->setTime(15, 0), 'live_at' => now()->subDays(2), 'fee_cents' => $sophie->feeCents(),
        ]);
        Payout::firstOrCreate(['collaboration_id' => $live->id], [
            'creator_id' => $sophie->id, 'amount_cents' => $live->fee_cents, 'run_on' => today()->isFriday() ? today() : today()->next('Friday'),
        ]);
        $this->klus($sophie, $bedrijf->id, $vestigingen[0]->id, 'MB-DEMO-P04', 'Lunchmenu', Status::BezoekGepland, [
            'visit_at' => now()->addDays(3)->setTime(11, 0), 'content_deadline_at' => now()->addDays(8)->setTime(17, 0), 'fee_cents' => $sophie->feeCents(),
        ]);
    }

    private function klus(Creator $creator, int $bedrijf, int $vestiging, string $nummer, string $titel, Status $status, array $extra): Collaboration
    {
        return Collaboration::firstOrCreate(['number' => $nummer], $extra + [
            'company_id' => $bedrijf, 'location_id' => $vestiging, 'creator_id' => $creator->id, 'title' => $titel,
            'briefing' => "Laat {$titel} zien.", 'status' => $status, 'creator_level' => $creator->level, 'points' => $creator->points(),
        ]);
    }
}
