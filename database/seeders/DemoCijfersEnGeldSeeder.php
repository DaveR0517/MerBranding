<?php

namespace Database\Seeders;

use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Creator;
use App\Models\CreditLedger;
use App\Models\DeliverableVersion;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payout;
use App\Models\StatSnapshot;
use App\Models\Status;
use App\Services\Geld;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Voorbeelddata voor de module cijfers en geld, bovenop DemoSeeder: twee samenwerkingen met cijfers,
 * een opeisbare vergoeding, facturen en aanmeldingen van de website. Alleen voor test en demo. Veilig om vaker te draaien.
 */
class DemoCijfersEnGeldSeeder extends Seeder
{
    public function run(): void
    {
        $bloesem = Company::where('name', 'Lunchroom Bloesem')->first();
        if (! $bloesem) {
            return;
        }
        $sophie = Creator::whereHas('user', fn ($q) => $q->where('email', 'sophie@'.DemoSeeder::DOMEIN))->first();
        $marit = Creator::whereHas('user', fn ($q) => $q->where('email', 'marit@'.DemoSeeder::DOMEIN))->first();
        $sanne = $bloesem->owners()->first();

        $sophie->forceFill(['payout_details' => 'NL00 DEMO 0000 0000 00 t.n.v. S. de Vries', 'vat_status' => 'Geen onderneming'])->save();

        // 1. Live, in de meetperiode: Sophie, TikTok (automatisch) en Instagram (handmatig, nog te controleren)
        $soep = $this->samenwerking($bloesem, $sophie, 'DEMO-D-0001', 'Wintersoep en appeltaart', 'Leeuwarden', Status::Live, now()->subDays(3));
        $tiktok = $soep->publications()->firstOrCreate(['platform' => 'tiktok'], ['url' => 'https://www.tiktok.com/@sophieeet/video/1', 'posted_at' => now()->subDays(3), 'last_found_at' => now()]);
        $insta = $soep->publications()->firstOrCreate(['platform' => 'instagram'], ['url' => 'https://www.instagram.com/p/demo-soep/', 'posted_at' => now()->subDays(3), 'last_found_at' => now()]);
        $this->metingen($tiktok, 'tiktok', ['views' => 18900, 'likes' => 1420, 'comments' => 96, 'shares' => 210], now()->subHours(5));
        $this->metingen($insta, StatSnapshot::HANDMATIG, ['views' => 9600, 'reach' => 7100, 'likes' => 640, 'comments' => 38, 'saves' => 236], now()->subHours(20), $sophie->user_id, $this->screenshot('DEMO-D-0001'));
        Payout::firstOrCreate(['collaboration_id' => $soep->id], ['creator_id' => $sophie->id, 'amount_cents' => $soep->fee_cents, 'run_on' => Geld::vrijdag(now()->subDays(3))]);

        // 2. Afgerond, met beoordeling: Marit, Instagram met geschat bereik
        $lunch = $this->samenwerking($bloesem, $marit, 'DEMO-D-0002', 'Lunch in Sneek', 'Sneek', Status::Afgerond, now()->subDays(20));
        $post = $lunch->publications()->firstOrCreate(['platform' => 'instagram'], ['url' => 'https://www.instagram.com/p/demo-sneek/', 'posted_at' => now()->subDays(20), 'last_found_at' => now()->subDays(6)]);
        $this->metingen($post, 'instagram', ['views' => 6300, 'reach' => 4100, 'likes' => 410, 'comments' => 22, 'shares' => 31, 'saves' => 17], now()->subDays(6), estimateReach: true);
        $lunch->review()->firstOrCreate([], ['user_id' => $sanne->id, 'rating' => 5, 'body' => 'Mooie video, veel reacties van gasten.']);

        // Facturen: vorige maand betaald, deze maand openstaand
        foreach ([[now()->subMonthNoOverflow()->startOfMonth(), Invoice::BETAALD], [now()->startOfMonth(), Invoice::OPENSTAAND]] as $i => [$start, $status]) {
            Invoice::firstOrCreate(['company_id' => $bloesem->id, 'period_start' => $start->toDateString()], [
                'number' => 'MB-'.$start->year.'-'.str_pad((string) (9000 + $i), 4, '0', STR_PAD_LEFT), 'period_end' => $start->copy()->endOfMonth()->toDateString(),
                'lines' => [['omschrijving' => 'Abonnement Groei, '.$start->translatedFormat('F Y'), 'toelichting' => '20 punten tegoed', 'aantal' => 1, 'prijs_cents' => 79500, 'bedrag_cents' => 79500]],
                'subtotal_cents' => 79500, 'vat_rate' => 21, 'vat_cents' => 16695, 'total_cents' => 96195, 'status' => $status,
                'issued_on' => $start->toDateString(), 'due_on' => $start->copy()->addDays(14)->toDateString(),
                'paid_at' => $status === Invoice::BETAALD ? $start->copy()->addDays(8) : null,
            ]);
        }

        // Aanmeldingen van de website, verspreid over de laatste weken
        if (Lead::where('email', 'like', '%@leads.'.DemoSeeder::DOMEIN)->doesntExist()) {
            foreach (range(0, 13) as $n) {
                Lead::create([
                    'type' => $n % 3 === 0 ? Lead::TYPE_CREATOR : Lead::TYPE_ONDERNEMER, 'name' => "Voorbeeld {$n}", 'email' => "lead{$n}@leads.".DemoSeeder::DOMEIN,
                ])->forceFill(['created_at' => now()->subDays($n * 4 + 1)])->save();
            }
        }
    }

    private function samenwerking(Company $b, Creator $creator, string $nummer, string $titel, string $plaats, Status $status, $live): Collaboration
    {
        $c = Collaboration::firstOrCreate(['number' => $nummer], [
            'company_id' => $b->id, 'location_id' => $b->locations()->where('name', $plaats)->value('id'), 'creator_id' => $creator->id,
            'requested_by' => $b->owners()->value('users.id'), 'title' => $titel, 'briefing' => 'Laat zien wat de zaak bijzonder maakt.',
            'status' => $status, 'points' => $creator->points(), 'creator_level' => $creator->level, 'fee_cents' => $creator->feeCents(),
            'visit_at' => $live->copy()->subDays(4), 'visited_at' => $live->copy()->subDays(4), 'publish_on' => $live->toDateString(),
            'live_at' => $live, 'measure_until' => $live->copy()->addDays(14), 'completed_at' => $status === Status::Afgerond ? $live->copy()->addDays(14) : null,
        ]);

        if ($c->wasRecentlyCreated) {
            $aangevraagd = $live->copy()->subDays(9);
            $c->forceFill(['created_at' => $aangevraagd])->save();
            CreditLedger::create(['company_id' => $b->id, 'collaboration_id' => $c->id, 'kind' => CreditLedger::RESERVEREN, 'points' => -$c->points, 'reason' => 'Aanvraag verstuurd: punten gereserveerd'])
                ->forceFill(['created_at' => $aangevraagd])->save();
            CreditLedger::create(['company_id' => $b->id, 'collaboration_id' => $c->id, 'kind' => CreditLedger::VERBRUIKEN, 'points' => 0, 'reason' => "Bezoek geweest: {$c->points} gereserveerde punten verbruikt"])
                ->forceFill(['created_at' => $c->visited_at])->save();
            $pad = "videos/{$nummer}/versie-1-demo.mp4";
            Storage::disk('local')->put($pad, '');
            DeliverableVersion::create([
                'collaboration_id' => $c->id, 'version' => 1, 'disk' => 'local', 'path' => $pad, 'mime' => 'video/mp4', 'size' => 0,
                'caption' => "Betaalde samenwerking met {$b->name}.", 'label_check' => ['geslaagd' => true], 'outcome' => 'goedgekeurd',
            ]);
        }

        return $c;
    }

    private function metingen($publicatie, string $bron, array $waarden, $op, ?int $door = null, ?string $screenshot = null, bool $estimateReach = false): void
    {
        if (StatSnapshot::where('publication_id', $publicatie->id)->exists()) {
            return;
        }
        foreach ($waarden as $metric => $waarde) {
            StatSnapshot::create([
                'publication_id' => $publicatie->id, 'metric' => $metric, 'value' => $waarde, 'source' => $bron, 'fetched_at' => $op,
                'is_estimate' => $estimateReach && $metric === 'reach', 'entered_by' => $door, 'screenshot_path' => $screenshot,
                'reviewed_at' => $bron === StatSnapshot::HANDMATIG ? null : $op,
            ]);
        }
    }

    /** Een klein voorbeeldplaatje als screenshot. */
    private function screenshot(string $nummer): string
    {
        $pad = "cijfers/{$nummer}/screenshot-demo.png";
        Storage::disk('local')->put($pad, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        return $pad;
    }
}
