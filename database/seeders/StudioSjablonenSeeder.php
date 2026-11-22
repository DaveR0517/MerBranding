<?php

namespace Database\Seeders;

use App\Models\StudioTemplate;
use App\Services\Studio\Merkkit;
use Illuminate\Database\Seeder;

/**
 * Huisstijlsjablonen voor MeStudio (eigen marketing van MEBRANDING), gebouwd met de merkkit: tekst op stijl,
 * logo en merkbalk vergrendeld, foto- en videovakken met een vast kader. Ze slagen voor de merkcontrole.
 * Idempotent: een sjabloon wordt alleen bijgewerkt als zijn seedversie ouder is; een nieuwe naam van het team blijft.
 * Eigen sjablonen van gebruikers (zonder slug) raakt dit nooit aan. Voorbeeldteksten in vakken zijn om te vervangen.
 */
class StudioSjablonenSeeder extends Seeder
{
    /** Ophogen als de lagen van deze sjablonen veranderen. */
    public const VERSIE = 2;

    private const AUBERGINE = '#3A3659';

    private const DONKER = '#2E2B48';

    private const LILA = '#6D6794';

    private const VIOLET = '#614ED9';

    private const ROOD = '#D9303E';

    private const LICHT = '#F6F5F9';

    private const WIT = '#FFFFFF';

    private Merkkit $m;

    public function run(): void
    {
        $this->m = app(Merkkit::class);

        foreach ($this->sjablonen() as $slug => [$naam, $formaat, $paginas]) {
            $sjabloon = StudioTemplate::firstOrNew(['slug' => $slug]);
            if ($sjabloon->exists && $sjabloon->seed_version >= self::VERSIE) {
                continue;
            }

            $f = config("studio.formaten.{$formaat}");
            $sjabloon->fill([
                'name' => $sjabloon->exists ? $sjabloon->name : $naam, 'format' => $formaat, 'width' => $f['breedte'], 'height' => $f['hoogte'],
                'pages' => $paginas, 'is_builtin' => true, 'seed_version' => self::VERSIE, 'preview_path' => null,
            ])->save();
        }
    }

    private function sjablonen(): array
    {
        $m = $this->m;

        return [
            'creator-in-de-spotlight' => ['Creator in de spotlight', 'instagram_portret', [$this->pagina(self::DONKER, [
                $m->vak('foto', 'Foto van de creator', 60, 60, 960, 900, 36),
                $m->verloop(100, 100, 560, 72, 36, naam: 'Labelvlak'),
                $m->tekst('Creator in de spotlight', 'label', 'instagram_portret', 100, 118, 560, self::WIT, 'center', naam: 'Label'),
                $m->tekst('Naam creator', 'kop', 'instagram_portret', 60, 1000, 960, self::WIT, vak: 'Naam creator'),
                $m->tekst('maakte een video bij Naam zaak in Plaats', 'tekst', 'instagram_portret', 60, 1100, 960, self::LICHT, vak: 'Zaak en plaats'),
                $m->logo('wit', 60, 1245, 280),
            ])]],

            'resultaat-in-cijfers' => ['Resultaat in cijfers', 'instagram_post', [$this->pagina(self::AUBERGINE, [
                $m->verloop(0, 0, 1080, 1080, 0, merk: true, naam: 'Merkverloop'),
                $m->tekst('Resultaat in cijfers', 'label', 'instagram_post', 60, 110, 960, self::WIT, 'center', naam: 'Label'),
                $m->tekst('1.234', 'cijfer', 'instagram_post', 60, 300, 960, self::WIT, 'center', vak: 'Getal (vul het echte resultaat in)'),
                $m->tekst('mensen bereikt met één video', 'subkop', 'instagram_post', 60, 560, 960, self::WIT, 'center', vak: 'Wat het getal is'),
                $m->tekst('Naam bedrijf, maand', 'label', 'instagram_post', 60, 660, 960, self::WIT, 'center', vak: 'Bedrijf en periode'),
                $m->logo('wit', 400, 975, 280),
            ])]],

            'nieuwe-partner' => ['Nieuwe partner', 'instagram_post', [$this->pagina(self::LICHT, [
                $m->vak('foto', 'Foto van de zaak', 0, 0, 1080, 620, 0),
                $m->vorm('rechthoek', 0, 620, 1080, 460, self::AUBERGINE),
                $m->verloop(60, 580, 360, 72, 36, naam: 'Labelvlak'),
                $m->tekst('Nieuwe partner', 'label', 'instagram_post', 60, 598, 360, self::WIT, 'center', naam: 'Label'),
                $m->tekst('Naam bedrijf', 'kop', 'instagram_post', 60, 690, 960, self::WIT, vak: 'Naam bedrijf'),
                $m->tekst('Welkom bij MEBRANDING. Binnenkort zie je hier video\'s van creators uit de regio.', 'tekst', 'instagram_post', 60, 800, 960, self::LICHT, vak: 'Welkomsttekst'),
                $m->logo('wit', 60, 975, 280),
            ])]],

            'video-teaser' => ['Video-teaser', 'story', [$this->pagina(self::DONKER, [
                $m->vak('video', 'Video', 0, 0, 1080, 1920, 0),
                $m->vorm('rechthoek', 0, 1040, 1080, 880, self::DONKER, dekking: 0.85),
                $m->logo('wit', 400, 270, 280),
                $m->verloop(60, 1100, 340, 72, 36, naam: 'Labelvlak'),
                $m->tekst('Nieuwe video', 'label', 'story', 60, 1118, 340, self::WIT, 'center', naam: 'Label'),
                $m->tekst('Zo ziet een dag bij Naam zaak eruit', 'kop', 'story', 60, 1210, 960, self::WIT, vak: 'Titel'),
                $m->tekst('Bekijk de hele video bij de creator', 'tekst', 'story', 60, 1420, 960, self::LICHT, vak: 'Onderregel'),
            ])]],

            'quote-van-een-ondernemer' => ['Quote van een ondernemer', 'instagram_portret', [$this->pagina(self::AUBERGINE, [
                $m->vorm('cirkel', 760, -160, 520, 520, self::VIOLET, dekking: 0.35),
                $m->verloop(60, 300, 120, 16, 8, naam: 'Accent'),
                $m->tekst('Hier komt de quote van de ondernemer, in zijn of haar eigen woorden.', 'kop', 'instagram_portret', 60, 360, 960, self::WIT, vak: 'Quote'),
                $m->vak('foto', 'Foto ondernemer', 60, 1000, 180, 180, 90),
                $m->tekst('Naam ondernemer', 'subkop', 'instagram_portret', 280, 1030, 740, self::WIT, vak: 'Naam'),
                $m->tekst('Eigenaar Naam bedrijf', 'tekst', 'instagram_portret', 280, 1095, 740, self::LICHT, vak: 'Functie en bedrijf'),
                $m->logo('wit', 60, 1245, 280),
            ])]],

            'word-creator' => ['Word creator', 'story', [$this->pagina(self::AUBERGINE, [
                $m->verloop(0, 0, 1080, 1920, 0, merk: true, naam: 'Merkverloop'),
                $m->logo('wit', 400, 270, 280),
                $m->vak('foto', 'Foto creator', 190, 380, 700, 640, 48),
                $m->tekst("Word creator bij\nMEBRANDING", 'kop', 'story', 60, 1060, 960, self::WIT, 'center', vak: 'Titel'),
                $m->tekst('Inspireer je volgers. Word beloond.', 'subkop', 'story', 60, 1260, 960, self::WIT, 'center', vak: 'Onderregel'),
                $m->vorm('rechthoek', 160, 1420, 760, 100, self::WIT, hoekafronding: 50),
                $m->tekst('Meld je aan op mebranding.nl', 'label', 'story', 160, 1452, 760, self::AUBERGINE, 'center', vak: 'Knoptekst'),
            ])]],

            'carrousel-zo-werkt-het' => ['Carrousel: zo werkt het', 'carrousel', [
                $this->pagina(self::AUBERGINE, [
                    $m->vorm('cirkel', -200, 900, 700, 700, self::VIOLET, dekking: 0.4),
                    $m->vorm('cirkel', 760, -120, 460, 460, self::ROOD, dekking: 0.35),
                    $m->tekst('Zo werkt het', 'label', 'carrousel', 60, 360, 960, self::LICHT, naam: 'Label'),
                    $m->tekst('Geen gedoe. Wij regelen alles.', 'kop', 'carrousel', 60, 430, 960, self::WIT, vak: 'Titel'),
                    $m->tekst('Swipe voor de stappen →', 'tekst', 'carrousel', 60, 1100, 960, self::LICHT, vak: 'Swipe-tekst'),
                    $m->logo('wit', 60, 1245, 280),
                ]),
                $this->pagina(self::LICHT, [
                    $m->vak('foto', 'Foto stap 1 en 2', 60, 60, 960, 560, 36),
                    $m->tekst('1', 'kop', 'carrousel', 60, 700, 110, self::VIOLET, naam: 'Nummer 1'),
                    $m->tekst('Je vertelt wat je wilt laten zien', 'subkop', 'carrousel', 180, 712, 840, self::AUBERGINE, vak: 'Stap 1'),
                    $m->tekst('2', 'kop', 'carrousel', 60, 880, 110, self::ROOD, naam: 'Nummer 2'),
                    $m->tekst('Wij koppelen een creator uit de regio', 'subkop', 'carrousel', 180, 892, 840, self::AUBERGINE, vak: 'Stap 2'),
                    $m->logo('aubergine', 60, 1245, 280),
                ]),
                $this->pagina(self::LICHT, [
                    $m->tekst('3', 'kop', 'carrousel', 60, 160, 110, self::VIOLET, naam: 'Nummer 3'),
                    $m->tekst('De creator komt langs en maakt de video', 'subkop', 'carrousel', 180, 172, 840, self::AUBERGINE, vak: 'Stap 3'),
                    $m->tekst('4', 'kop', 'carrousel', 60, 400, 110, self::ROOD, naam: 'Nummer 4'),
                    $m->tekst('Je ziet in je dashboard wat het oplevert', 'subkop', 'carrousel', 180, 412, 840, self::AUBERGINE, vak: 'Stap 4'),
                    $m->verloop(60, 700, 960, 300, 36, naam: 'Afsluitvlak'),
                    $m->tekst('Plan een kennismaking op mebranding.nl', 'subkop', 'carrousel', 120, 790, 840, self::WIT, 'center', vak: 'Afsluiter'),
                    $m->logo('aubergine', 60, 1245, 280),
                ]),
            ]],

            'aankondiging' => ['Aankondiging', 'instagram_post', [$this->pagina(self::WIT, [
                $m->verloop(0, 0, 1080, 24, 0, merk: true, naam: 'Merkbalk'),
                $m->vorm('cirkel', 700, 640, 620, 620, self::LICHT),
                $m->vorm('rechthoek', 60, 140, 330, 72, self::AUBERGINE, hoekafronding: 36),
                $m->tekst('Aankondiging', 'label', 'instagram_post', 60, 158, 330, self::WIT, 'center', naam: 'Label'),
                $m->tekst('Hier komt je aankondiging', 'kop', 'instagram_post', 60, 280, 960, self::AUBERGINE, vak: 'Titel'),
                $m->tekst('Een korte toelichting voor ondernemers en creators in Noord-Nederland.', 'tekst', 'instagram_post', 60, 500, 760, self::LILA, vak: 'Tekst'),
                $m->logo('aubergine', 60, 975, 280),
            ])]],
        ];
    }

    private function pagina(string $achtergrond, array $lagen): array
    {
        return ['achtergrond' => $achtergrond, 'lagen' => $lagen];
    }
}
