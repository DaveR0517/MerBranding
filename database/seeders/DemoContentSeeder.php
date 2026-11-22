<?php

namespace Database\Seeders;

use App\Models\Collaboration;
use App\Models\Company;
use App\Models\ContentNote;
use App\Models\Creator;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Status;
use App\Models\VideoIdea;
use App\Services\Gebruiksrechten;
use Illuminate\Database\Seeder;

/**
 * Demodata voor de module content en koppelingen (los aanroepbaar, idempotent). Draai eerst DemoSeeder.
 * Samenwerkingen worden hier direct in een status gezet, alleen voor de demo; op het platform gaat dat altijd via App\Services\Samenwerking.
 */
class DemoContentSeeder extends Seeder
{
    public function run(Gebruiksrechten $rechten): void
    {
        $bloesem = Company::where('name', 'Lunchroom Bloesem')->firstOrFail();
        $sophie = Creator::whereHas('user', fn ($q) => $q->where('email', 'sophie@'.DemoSeeder::DOMEIN))->firstOrFail();
        $marit = Creator::whereHas('user', fn ($q) => $q->where('email', 'marit@'.DemoSeeder::DOMEIN))->firstOrFail();
        [$leeuwarden, $sneek, $franeker] = $bloesem->locations()->orderBy('id')->get()->all();
        $maandag = today()->startOfWeek();

        $live = $this->samenwerking('DEMO-H1', $bloesem, $leeuwarden, $sophie, Status::Live, 'Wintersoep en appeltaart', [
            'visit_at' => today()->subDays(12)->setTime(12, 0), 'publish_on' => today()->subDays(3), 'live_at' => today()->subDays(3)->setTime(18, 0),
            'measure_until' => today()->addDays(11)->setTime(18, 0), 'fee_cents' => $sophie->feeCents(),
        ]);
        Publication::firstOrCreate(['collaboration_id' => $live->id], ['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@sophieproeft/video/1', 'posted_at' => $live->live_at, 'last_found_at' => now()->subHours(5)]);
        $rechten->standaard($live, today()->subMonths(3)->addDays(20));

        $this->samenwerking('DEMO-H2', $bloesem, $sneek, $marit, Status::BezoekGepland, 'Lunch in Sneek', ['visit_at' => $maandag->copy()->addDays(2)->setTime(12, 30)]);
        $this->samenwerking('DEMO-H3', $bloesem, $franeker, $sophie, Status::Goedgekeurd, 'Kerstlunch Franeker', [
            'visit_at' => today()->subDays(6)->setTime(11, 0), 'publish_on' => today()->addDays(4),
        ]);
        $this->samenwerking('DEMO-H4', $bloesem, $leeuwarden, $marit, Status::CreatorGevonden, 'Brunch op zondag', []);

        ContentNote::firstOrCreate(['company_id' => $bloesem->id, 'title' => 'Kerstmenu laten zien'], ['date' => today()->addDays(9), 'body' => 'Idee voor een video in december.']);
        VideoIdea::firstOrCreate(['company_id' => $bloesem->id, 'title' => 'Van pan tot kom'], ['description' => 'De soep van de dag, van snijplank tot de kom op tafel.', 'source' => 'ondernemer', 'status' => 'idee']);
        VideoIdea::firstOrCreate(['company_id' => $bloesem->id, 'title' => 'Appeltaart in 15 seconden'], ['description' => 'Snelle hook: het eerste stuk uit de vorm.', 'source' => 'team', 'status' => 'gepland', 'collaboration_id' => $live->id]);

        SocialAccount::firstOrCreate(['owner_type' => $sophie->getMorphClass(), 'owner_id' => $sophie->id, 'platform' => 'tiktok'], [
            'handle' => '@sophieproeft', 'professional_confirmed' => true, 'status' => 'fout', 'last_error' => 'Toegang ingetrokken, 3 keer op rij mislukt (voorbeeld)',
            'consent_version' => config('content.consent_version'), 'consent_at' => now()->subMonth(), 'last_synced_at' => now()->subDays(2),
        ]);
        SocialAccount::firstOrCreate(['owner_type' => $sophie->getMorphClass(), 'owner_id' => $sophie->id, 'platform' => 'instagram'], [
            'handle' => '@sophieproeft', 'professional_confirmed' => true, 'status' => 'gekoppeld', 'access_token' => 'demo-token', 'expires_at' => now()->addDays(5),
            'consent_version' => config('content.consent_version'), 'consent_at' => now()->subDays(55), 'last_synced_at' => now()->subHours(3),
        ]);
    }

    private function samenwerking(string $nummer, Company $bedrijf, $vestiging, Creator $creator, Status $status, string $titel, array $extra): Collaboration
    {
        return Collaboration::firstOrCreate(['number' => $nummer], $extra + [
            'company_id' => $bedrijf->id, 'location_id' => $vestiging->id, 'creator_id' => $creator->id, 'title' => $titel,
            'briefing' => 'Demo: laat zien wat de gast ervaart.', 'status' => $status->value, 'points' => $creator->points(), 'creator_level' => $creator->level,
        ]);
    }
}
