<?php

namespace Database\Seeders;

use App\Models\StudioTemplate;
use Illuminate\Database\Seeder;

/**
 * Huisstijlsjablonen voor MeStudio (eigen marketing van MEBRANDING). Idempotent: bestaande sjablonen
 * (op slug) blijven zoals het team ze heeft aangepast. Voorbeeldteksten in vakken zijn bedoeld om te vervangen.
 */
class StudioSjablonenSeeder extends Seeder
{
    private const AUBERGINE = '#3A3659';

    private const DONKER = '#2E2B48';

    private const LILA = '#6D6794';

    private const VIOLET = '#614ED9';

    private const ROOD = '#D9303E';

    private const LICHT = '#F6F5F9';

    private const WIT = '#FFFFFF';

    private int $teller = 0;

    public function run(): void
    {
        foreach ($this->sjablonen() as $slug => [$naam, $formaat, $paginas]) {
            $f = config("studio.formaten.{$formaat}");
            StudioTemplate::firstOrCreate(['slug' => $slug], [
                'name' => $naam, 'format' => $formaat, 'width' => $f['breedte'], 'height' => $f['hoogte'],
                'pages' => $paginas, 'is_builtin' => true,
            ]);
        }
    }

    private function sjablonen(): array
    {
        return [
            'creator-in-de-spotlight' => ['Creator in de spotlight', 'instagram_portret', [$this->pagina(self::DONKER, [
                $this->fotovak('Foto van de creator', 60, 60, 960, 930, 36),
                $this->verloop(100, 100, 470, 70, 35),
                $this->tekst('CREATOR IN DE SPOTLIGHT', 100, 121, 470, 28, 800, self::WIT, 'center', naam: 'Label'),
                $this->tekst('Naam creator', 60, 1030, 960, 80, 800, self::WIT, vak: 'Naam creator'),
                $this->tekst('maakte een video bij Naam zaak in Plaats', 60, 1130, 960, 40, 400, self::LICHT, vak: 'Zaak en plaats', lettertype: 'Noto Sans Variable'),
                $this->logo('mebranding-logo-wit.svg', 60, 1250, 314),
            ])]],

            'resultaat-in-cijfers' => ['Resultaat in cijfers', 'instagram_post', [$this->pagina(self::AUBERGINE, [
                $this->verloop(0, 0, 1080, 1080, 0),
                $this->tekst('RESULTAAT IN CIJFERS', 80, 110, 920, 34, 800, self::WIT, 'center', naam: 'Label'),
                $this->tekst('1.234', 40, 330, 1000, 230, 900, self::WIT, 'center', vak: 'Getal (vul het echte resultaat in)', regelafstand: 1),
                $this->tekst('mensen bereikt met één video', 80, 590, 920, 56, 700, self::WIT, 'center', vak: 'Wat het getal is'),
                $this->tekst('Naam bedrijf, maand', 80, 690, 920, 36, 400, self::LICHT, 'center', vak: 'Bedrijf en periode', lettertype: 'Noto Sans Variable'),
                $this->logo('mebranding-logo-wit.svg', 383, 960, 314),
            ])]],

            'nieuwe-partner' => ['Nieuwe partner', 'instagram_post', [$this->pagina(self::LICHT, [
                $this->fotovak('Foto van de zaak', 0, 0, 1080, 620, 0),
                $this->vorm('rechthoek', 0, 620, 1080, 460, self::AUBERGINE),
                $this->verloop(80, 560, 360, 72, 36),
                $this->tekst('NIEUWE PARTNER', 80, 583, 360, 28, 800, self::WIT, 'center', naam: 'Label'),
                $this->tekst('Naam bedrijf', 80, 690, 920, 84, 800, self::WIT, vak: 'Naam bedrijf'),
                $this->tekst('Welkom bij MEBRANDING. Binnenkort zie je hier video\'s van creators uit de regio.', 80, 800, 920, 38, 400, self::LICHT, vak: 'Welkomsttekst', lettertype: 'Noto Sans Variable', regelafstand: 1.35),
                $this->logo('mebranding-logo-wit.svg', 80, 975, 280),
            ])]],

            'video-teaser' => ['Video-teaser', 'story', [$this->pagina(self::DONKER, [
                $this->videovak('Video', 0, 0, 1080, 1920, 0),
                $this->vorm('rechthoek', 0, 1260, 1080, 660, self::DONKER, dekking: 0.72),
                $this->logo('mebranding-logo-wit.svg', 383, 120, 314),
                $this->verloop(80, 1330, 340, 72, 36),
                $this->tekst('NIEUWE VIDEO', 80, 1353, 340, 28, 800, self::WIT, 'center', naam: 'Label'),
                $this->tekst('Zo ziet een dag bij Naam zaak eruit', 80, 1450, 920, 88, 800, self::WIT, vak: 'Titel', regelafstand: 1.1),
                $this->tekst('Bekijk de hele video bij de creator', 80, 1700, 920, 40, 400, self::LICHT, vak: 'Onderregel', lettertype: 'Noto Sans Variable'),
            ])]],

            'quote-van-een-ondernemer' => ['Quote van een ondernemer', 'instagram_portret', [$this->pagina(self::AUBERGINE, [
                $this->vorm('cirkel', 760, -160, 520, 520, self::VIOLET, dekking: 0.35),
                $this->tekst('“', 70, 90, 300, 320, 900, self::ROOD, naam: 'Aanhalingsteken', regelafstand: 1),
                $this->tekst('Hier komt de quote van de ondernemer, in zijn of haar eigen woorden.', 80, 380, 920, 64, 700, self::WIT, vak: 'Quote', regelafstand: 1.25),
                $this->fotovak('Foto ondernemer', 80, 1000, 180, 180, 90),
                $this->tekst('Naam ondernemer', 300, 1030, 700, 44, 800, self::WIT, vak: 'Naam'),
                $this->tekst('Eigenaar Naam bedrijf', 300, 1090, 700, 34, 400, self::LICHT, vak: 'Functie en bedrijf', lettertype: 'Noto Sans Variable'),
                $this->logo('mebranding-logo-wit.svg', 80, 1250, 280),
            ])]],

            'word-creator' => ['Word creator', 'story', [$this->pagina(self::AUBERGINE, [
                $this->verloop(0, 0, 1080, 1920, 0),
                $this->logo('mebranding-logo-wit.svg', 383, 120, 314),
                $this->fotovak('Foto creator', 140, 300, 800, 800, 48),
                $this->tekst("Word creator bij\nMEBRANDING", 80, 1160, 920, 88, 900, self::WIT, 'center', vak: 'Titel', regelafstand: 1.05),
                $this->tekst('Inspireer je volgers. Word beloond.', 80, 1400, 920, 48, 700, self::WIT, 'center', vak: 'Onderregel'),
                $this->vorm('rechthoek', 240, 1600, 600, 110, self::WIT, hoekafronding: 55),
                $this->tekst('Meld je aan op mebranding.nl', 240, 1638, 600, 34, 800, self::AUBERGINE, 'center', vak: 'Knoptekst'),
            ])]],

            'carrousel-zo-werkt-het' => ['Carrousel: zo werkt het', 'carrousel', [
                $this->pagina(self::AUBERGINE, [
                    $this->vorm('cirkel', -200, 900, 700, 700, self::VIOLET, dekking: 0.4),
                    $this->vorm('cirkel', 760, -120, 460, 460, self::ROOD, dekking: 0.35),
                    $this->tekst('ZO WERKT HET', 80, 360, 920, 34, 800, self::LICHT, naam: 'Label'),
                    $this->tekst('Geen gedoe. Wij regelen alles.', 80, 430, 920, 110, 900, self::WIT, vak: 'Titel', regelafstand: 1.05),
                    $this->tekst('Swipe voor de stappen →', 80, 1100, 920, 40, 600, self::LICHT, vak: 'Swipe-tekst', lettertype: 'Noto Sans Variable'),
                    $this->logo('mebranding-logo-wit.svg', 80, 1250, 280),
                ]),
                $this->pagina(self::LICHT, [
                    $this->fotovak('Foto stap 1 en 2', 80, 80, 920, 560, 36),
                    $this->tekst('1', 80, 700, 120, 90, 900, self::VIOLET, naam: 'Nummer 1', regelafstand: 1),
                    $this->tekst('Je vertelt wat je wilt laten zien', 200, 712, 800, 48, 800, self::AUBERGINE, vak: 'Stap 1'),
                    $this->tekst('2', 80, 900, 120, 90, 900, self::ROOD, naam: 'Nummer 2', regelafstand: 1),
                    $this->tekst('Wij koppelen een creator uit de regio', 200, 912, 800, 48, 800, self::AUBERGINE, vak: 'Stap 2'),
                    $this->logo('mebranding-logo-aubergine.svg', 80, 1250, 280),
                ]),
                $this->pagina(self::LICHT, [
                    $this->tekst('3', 80, 160, 120, 90, 900, self::VIOLET, naam: 'Nummer 3', regelafstand: 1),
                    $this->tekst('De creator komt langs en maakt de video', 200, 172, 800, 48, 800, self::AUBERGINE, vak: 'Stap 3'),
                    $this->tekst('4', 80, 400, 120, 90, 900, self::ROOD, naam: 'Nummer 4', regelafstand: 1),
                    $this->tekst('Je ziet in je dashboard wat het oplevert', 200, 412, 800, 48, 800, self::AUBERGINE, vak: 'Stap 4'),
                    $this->verloop(80, 780, 920, 300, 36),
                    $this->tekst('Plan een kennismaking op mebranding.nl', 140, 870, 800, 52, 800, self::WIT, 'center', vak: 'Afsluiter', regelafstand: 1.15),
                    $this->logo('mebranding-logo-aubergine.svg', 80, 1250, 280),
                ]),
            ]],

            'aankondiging' => ['Aankondiging', 'instagram_post', [$this->pagina(self::WIT, [
                $this->verloop(0, 0, 1080, 24, 0),
                $this->vorm('cirkel', 700, 640, 620, 620, self::LICHT),
                $this->vorm('rechthoek', 80, 140, 330, 70, self::AUBERGINE, hoekafronding: 35),
                $this->tekst('AANKONDIGING', 80, 162, 330, 28, 800, self::WIT, 'center', naam: 'Label'),
                $this->tekst('Hier komt je aankondiging', 80, 280, 920, 96, 900, self::AUBERGINE, vak: 'Titel', regelafstand: 1.05),
                $this->tekst('Een korte toelichting voor ondernemers en creators in Noord-Nederland.', 80, 560, 760, 42, 400, self::LILA, vak: 'Tekst', lettertype: 'Noto Sans Variable', regelafstand: 1.35),
                $this->logo('mebranding-logo-aubergine.svg', 80, 960, 314),
            ])]],
        ];
    }

    private function pagina(string $achtergrond, array $lagen): array
    {
        return ['achtergrond' => $achtergrond, 'lagen' => $lagen];
    }

    private function basis(string $soort, string $naam, float $x, float $y, float $w, float $h): array
    {
        return ['id' => $soort.'-'.(++$this->teller), 'soort' => $soort, 'naam' => $naam, 'x' => $x, 'y' => $y,
            'breedte' => $w, 'hoogte' => $h, 'rotatie' => 0, 'zichtbaar' => true, 'vergrendeld' => false];
    }

    private function tekst(string $tekst, float $x, float $y, float $w, int $grootte, int $gewicht, string $kleur, string $uitlijning = 'left',
        ?string $vak = null, ?string $naam = null, string $lettertype = 'Montserrat Variable', float $regelafstand = 1.2): array
    {
        return $this->basis('tekst', $vak ?? $naam ?? 'Tekst', $x, $y, $w, $grootte * $regelafstand) + [
            'tekst' => $tekst, 'lettertype' => $lettertype, 'grootte' => $grootte, 'gewicht' => $gewicht, 'kleur' => $kleur,
            'uitlijning' => $uitlijning, 'regelafstand' => $regelafstand, 'vak' => $vak !== null, 'vakNaam' => $vak,
        ];
    }

    private function fotovak(string $naam, float $x, float $y, float $w, float $h, float $hoek): array
    {
        return $this->basis('foto', $naam, $x, $y, $w, $h) + ['bron' => null, 'uitsnede' => ['x' => 0.5, 'y' => 0.5, 'zoom' => 1],
            'hoekafronding' => $hoek, 'vak' => true, 'vakNaam' => $naam];
    }

    private function videovak(string $naam, float $x, float $y, float $w, float $h, float $hoek): array
    {
        return ['soort' => 'video'] + $this->fotovak($naam, $x, $y, $w, $h, $hoek);
    }

    private function vorm(string $vorm, float $x, float $y, float $w, float $h, string $kleur, float $hoekafronding = 0, float $dekking = 1): array
    {
        return $this->basis('vorm', $vorm === 'cirkel' ? 'Cirkel' : 'Rechthoek', $x, $y, $w, $h) + [
            'vorm' => $vorm, 'vulling' => $kleur, 'hoekafronding' => $hoekafronding, 'dekking' => $dekking,
        ];
    }

    private function verloop(float $x, float $y, float $w, float $h, float $hoek): array
    {
        return $this->basis('verloop', 'Merkverloop', $x, $y, $w, $h) + ['van' => self::ROOD, 'naar' => self::VIOLET, 'hoek' => 135, 'hoekafronding' => $hoek];
    }

    /** Logo uit public/brand; de woordmerken zijn 627,4 bij 100 (verhouding vast). */
    private function logo(string $bestand, float $x, float $y, float $w): array
    {
        return $this->basis('logo', 'Logo', $x, $y, $w, round($w / 6.274)) + ['bron' => ['soort' => 'logo', 'naam' => $bestand]];
    }
}
