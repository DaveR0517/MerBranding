<?php

namespace Database\Seeders;

use App\Models\KanaalBericht;
use App\Models\KanaalGesprek;
use App\Models\KanaalMedia;
use App\Models\KanaalMeting;
use App\Models\KanaalReactie;
use App\Models\SocialAccount;
use Illuminate\Database\Seeder;

/**
 * VOORBEELD-data voor de module eigen kanalen (alleen lokaal en voor schermafbeeldingen). Veilig om vaker te draaien.
 * Alles is herkenbaar als voorbeeld: handles met "voorbeeld", ids die beginnen met "voorbeeld-" en geen tokens,
 * zodat het uurlijkse ophalen deze accounts overslaat en er niets naar Meta of TikTok gaat.
 */
class DemoKanalenSeeder extends Seeder
{
    public function run(): void
    {
        $ig = $this->account('instagram', '@mebranding.voorbeeld', 'MEBRANDING (voorbeeld)', config('kanalen.scopes.instagram'));
        $tt = $this->account('tiktok', '@mebranding.voorbeeld', 'MEBRANDING (voorbeeld)', config('kanalen.scopes.tiktok'));

        // 29 dagen verloop: volgers groeien, weergaven per dag schommelen met het weekend.
        for ($d = 28; $d >= 0; $d--) {
            $dag = today()->subDays($d);
            $golf = 1 + 0.35 * sin($d / 2.3) + ($dag->isWeekend() ? 0.25 : 0);
            KanaalMeting::zet($ig->id, $dag->toDateString(), 'volgers', 2140 + (28 - $d) * 6);
            KanaalMeting::zet($tt->id, $dag->toDateString(), 'volgers', 5310 + (28 - $d) * 19);
            KanaalMeting::zet($tt->id, $dag->toDateString(), 'video_weergaven_totaal', 180000 + (int) round((28 - $d) * 2400 * $golf));
            if ($d > 0) {
                KanaalMeting::zet($ig->id, $dag->toDateString(), 'weergaven', (int) round(1850 * $golf));
                KanaalMeting::zet($ig->id, $dag->toDateString(), 'bereik', (int) round(960 * $golf));
                KanaalMeting::zet($ig->id, $dag->toDateString(), 'accounts_betrokken', (int) round(88 * $golf));
            }
        }
        KanaalMeting::zet($tt->id, today()->toDateString(), 'likes_totaal', 48200);

        $titels = ['Zo werkt een samenwerking met een creator', 'Achter de schermen bij Lunchroom Bloesem', '3 tips voor je eerste reel', 'Creator Sophie op pad in Franeker',
            'Wat kost lokale zichtbaarheid?', 'Klantverhaal: Bakkerij de Jong', 'Kerstcampagne in 60 seconden', 'Zo kies je de juiste creator', 'Onze nieuwe Academy-lichting',
            'Before en after: een menukaart', 'Dit vroegen ondernemers deze week', 'Friesland op z\'n mooist', 'Vijf fouten in je bio', 'Team MEBRANDING stelt zich voor'];
        $soorten = [['VIDEO', 'REELS'], ['IMAGE', 'FEED'], ['CAROUSEL_ALBUM', 'FEED'], ['VIDEO', 'REELS']];
        foreach ($titels as $i => $titel) {
            [$soort, $product] = $soorten[$i % 4];
            $bereik = 600 + (($i * 397) % 2600);
            $this->media($ig, "voorbeeld-ig-{$i}", $soort, $product, "{$titel} (voorbeeld)", $i * 2 + 1, $i, [
                'views' => (int) ($bereik * ($product === 'REELS' ? 2.4 : 1.3)), 'reach' => $bereik, 'likes' => (int) ($bereik * 0.07), 'comments' => 3 + $i % 9,
                'saved' => (int) ($bereik * 0.02), 'shares' => (int) ($bereik * 0.015), 'total_interactions' => (int) ($bereik * 0.11),
            ]);
        }
        foreach (array_slice($titels, 0, 8) as $i => $titel) {
            $views = 1800 + (($i * 5113) % 24000);
            $this->media($tt, "voorbeeld-tt-{$i}", 'VIDEO', null, "{$titel} #mebranding (voorbeeld)", $i * 3 + 2, $i + 3, [
                'views' => $views, 'likes' => (int) ($views * 0.06), 'comments' => (int) ($views * 0.004), 'shares' => (int) ($views * 0.003),
            ]);
        }

        // Reacties onder de nieuwste post.
        $nieuwste = KanaalMedia::where('extern_id', 'voorbeeld-ig-0')->first();
        $reacties = [['voorbeeld-c1', null, 'lunchroombloesem', 'Wat een leuke video! Wanneer komen jullie weer langs?', false],
            ['voorbeeld-c2', 'voorbeeld-c1', 'mebranding.voorbeeld', 'Dank je! We plannen in januari een nieuwe opname.', true],
            ['voorbeeld-c3', null, 'bakkerijdejong', 'Kunnen wij ook meedoen in Leeuwarden?', false]];
        foreach ($reacties as $n => [$id, $ouder, $naam, $tekst, $eigen]) {
            KanaalReactie::updateOrCreate(['extern_id' => $id], ['kanaal_media_id' => $nieuwste->id, 'ouder_extern_id' => $ouder, 'gebruikersnaam' => $naam, 'tekst' => $tekst, 'eigen' => $eigen, 'likes' => 2 - min(2, $n), 'geplaatst_op' => now()->subHours(20 - $n * 3)]);
        }

        // Gesprekken: één ongelezen en binnen het venster, één binnen het venster, één erbuiten.
        $this->gesprek($ig, 'voorbeeld-igsid-1', 'kapsalonrood', 1, [['in', 'Hoi! Wat kost een samenwerking voor een kapsalon in Sneek?', 50]]);
        $this->gesprek($ig, 'voorbeeld-igsid-2', 'bakkerijdejong', 0, [['in', 'Werken jullie ook met bakkerijen?', 300], ['uit', 'Zeker! Zal ik je bellen om het uit te leggen?', 280], ['in', 'Graag, morgen na 14 uur.', 240]]);
        $this->gesprek($ig, 'voorbeeld-igsid-3', 'creator.sophie', 0, [['in', 'Ik zou graag creator worden bij jullie.', 60 * 40], ['uit', 'Leuk! Meld je aan via de website, dan nemen we contact op.', 60 * 39]]);
    }

    /** @param  list<string>  $scopes */
    private function account(string $platform, string $handle, string $naam, array $scopes): SocialAccount
    {
        return SocialAccount::eigen()->updateOrCreate(['owner_type' => SocialAccount::EIGEN, 'owner_id' => 0, 'platform' => $platform], [
            'handle' => $handle, 'external_id' => "voorbeeld-{$platform}", 'scopes' => $scopes, 'profiel' => ['naam' => $naam, 'foto' => null],
            'status' => 'gekoppeld', 'professional_confirmed' => true, 'access_token' => null, 'refresh_token' => null,
            'expires_at' => $platform === 'instagram' ? now()->addDays(52) : now()->addDays(340), 'last_synced_at' => now()->subMinutes(17), 'last_error' => null,
            'consent_version' => config('content.consent_version'), 'consent_at' => now()->subDays(8),
        ]);
    }

    /** @param  array<string, int>  $cijfers */
    private function media(SocialAccount $a, string $id, string $soort, ?string $product, string $bijschrift, int $dagenGeleden, int $kleur, array $cijfers): void
    {
        KanaalMedia::updateOrCreate(['social_account_id' => $a->id, 'extern_id' => $id], [
            'soort' => $soort, 'product' => $product, 'bijschrift' => $bijschrift, 'miniatuur_url' => $this->miniatuur($kleur, $a->platform),
            'permalink' => $a->platform === 'instagram' ? 'https://www.instagram.com/' : 'https://www.tiktok.com/', 'duur' => $a->platform === 'tiktok' ? 20 + $kleur * 4 : null,
            'geplaatst_op' => now()->subDays($dagenGeleden)->setTime(12 + $kleur % 6, 15), 'cijfers' => $cijfers, 'cijfers_op' => now()->subMinutes(17),
        ]);
    }

    /** Miniatuur als SVG in de huisstijl met het woord VOORBEELD, zodat niemand het voor echt aanziet. */
    private function miniatuur(int $i, string $platform): string
    {
        $kleuren = [['#D9303E', '#614ED9'], ['#614ED9', '#2A1B3D'], ['#2A1B3D', '#D9303E']];
        [$van, $naar] = $kleuren[$i % 3];
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 500'><defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='{$van}'/><stop offset='1' stop-color='{$naar}'/></linearGradient></defs>"
            ."<rect width='400' height='500' fill='url(#g)'/><text x='200' y='240' font-family='sans-serif' font-size='34' font-weight='700' fill='white' text-anchor='middle'>VOORBEELD</text>"
            ."<text x='200' y='285' font-family='sans-serif' font-size='22' fill='white' fill-opacity='0.8' text-anchor='middle'>".($platform === 'instagram' ? 'Instagram' : 'TikTok').' '.($i + 1).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** @param  list<array{0: string, 1: string, 2: int}>  $berichten  richting, tekst, minuten geleden */
    private function gesprek(SocialAccount $a, string $deelnemer, string $naam, int $ongelezen, array $berichten): void
    {
        $inkomend = collect($berichten)->where(0, 'in')->min(2);
        $g = KanaalGesprek::updateOrCreate(['social_account_id' => $a->id, 'deelnemer_id' => $deelnemer], [
            'deelnemer_naam' => $naam, 'ongelezen' => $ongelezen, 'laatste_bericht_op' => now()->subMinutes(collect($berichten)->min(2)), 'laatste_inkomend_op' => now()->subMinutes($inkomend),
        ]);
        foreach ($berichten as $n => [$richting, $tekst, $minuten]) {
            KanaalBericht::updateOrCreate(['extern_id' => "{$deelnemer}-{$n}"], ['kanaal_gesprek_id' => $g->id, 'richting' => $richting, 'tekst' => $tekst, 'verzonden_op' => now()->subMinutes($minuten)]);
        }
    }
}
