<?php

namespace App\Services\Studio;

use App\Models\ActivityLog;
use App\Models\StudioMerkkit;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

/**
 * Merkkit van MeStudio: de ENE bron voor kleuren, lettertypes, tekststijlen, logo's, marges en veilige zones.
 * Standaard uit config/studio.php, aangepast door Super Admin (tabel studio_merkkit, elke wijziging in het logboek).
 * Ook de bouwstenen van lagen (tekst op stijl, logo, merkbalk, vakken) staan hier, zodat merkbasissen en
 * huisstijlsjablonen dezelfde regels volgen.
 */
class Merkkit
{
    public const STIJLEN = ['kop', 'subkop', 'tekst', 'label', 'cijfer'];

    public const BASISSEN = ['licht' => 'Licht', 'donker' => 'Donker', 'verloop' => 'Verloop'];

    private ?array $huidig = null;

    private int $teller = 0;

    public function standaard(): array
    {
        return config('studio.merkkit');
    }

    /** De geldende merkkit: standaard met de aanpassingen eroverheen. Lijsten (palet) worden in zijn geheel vervangen. */
    public function huidig(): array
    {
        if ($this->huidig !== null) {
            return $this->huidig;
        }

        $standaard = $this->standaard();

        try {
            $opgeslagen = StudioMerkkit::latest('id')->value('data') ?? [];
            $opgeslagen = is_string($opgeslagen) ? json_decode($opgeslagen, true) : $opgeslagen;
        } catch (Throwable) {
            $opgeslagen = [];
        }

        $kit = array_replace_recursive($standaard, Arr::except($opgeslagen, ['palet', 'lettertypes']));
        $kit['palet'] = $opgeslagen['palet'] ?? $standaard['palet'];
        $kit['lettertypes'] = $standaard['lettertypes'];

        return $this->huidig = $kit;
    }

    /** Opslaan door Super Admin; elke gewijzigde waarde komt met oud en nieuw in het logboek. */
    public function opslaan(array $nieuw, User $door): int
    {
        $oud = Arr::dot($this->huidig());
        $nieuw['lettertypes'] = $this->standaard()['lettertypes'];
        StudioMerkkit::query()->delete();
        StudioMerkkit::create(['data' => $nieuw, 'user_id' => $door->id]);
        $this->huidig = null;
        $na = Arr::dot($this->huidig());

        $wijzigingen = collect(array_keys($oud + $na))
            ->filter(fn ($k) => ($oud[$k] ?? null) !== ($na[$k] ?? null))
            ->mapWithKeys(fn ($k) => [$k => ['oud' => $oud[$k] ?? null, 'nieuw' => $na[$k] ?? null]]);

        if ($wijzigingen->isNotEmpty()) {
            ActivityLog::create([
                'user_id' => $door->id, 'event' => 'merkkit_gewijzigd',
                'reason' => 'Merkkit MeStudio aangepast ('.$wijzigingen->count().' waarden)',
                'data' => ['wijzigingen' => $wijzigingen->all()],
            ]);
        }

        return $wijzigingen->count();
    }

    /** Maat, marge, veilige zones en schaal van een formaat. */
    public function formaat(string $formaat): array
    {
        return config("studio.formaten.{$formaat}") + ($this->huidig()['formaten'][$formaat] ?? ['marge' => 60, 'veilig_boven' => 0, 'veilig_onder' => 0, 'schaal' => 1]);
    }

    /** Waarden van een tekststijl in een formaat. */
    public function stijl(string $stijl, string $formaat): array
    {
        $s = $this->huidig()['stijlen'][$stijl] ?? $this->huidig()['stijlen']['tekst'];

        return [
            'lettertype' => $s['lettertype'], 'grootte' => (int) round($s['grootte'] * $this->formaat($formaat)['schaal']),
            'gewicht' => (int) $s['gewicht'], 'regelafstand' => (float) $s['regelafstand'], 'hoofdletters' => (bool) $s['hoofdletters'],
        ];
    }

    /** Tekstlagen met een stijl (en niet vrij gezet door Super Admin) krijgen de waarden van de merkkit. */
    public function pasToe(array $paginas, string $formaat): array
    {
        return array_map(fn ($p) => ['lagen' => array_map(function ($l) use ($formaat) {
            if (($l['soort'] ?? null) === 'tekst' && in_array($l['stijl'] ?? null, self::STIJLEN, true) && empty($l['vrij'])) {
                $l = array_merge($l, $this->stijl($l['stijl'], $formaat));
            }

            return $l;
        }, $p['lagen'] ?? [])] + $p, $paginas);
    }

    /** Een nieuw ontwerp begint nooit blanco: achtergrond uit het palet, merkbalk of verloop, logo, Kop en Tekst. */
    public function basis(string $formaat, string $soort): array
    {
        $f = $this->formaat($formaat);
        [$W, $H, $m] = [$f['breedte'], $f['hoogte'], $f['marge']];
        $boven = max($m, $f['veilig_boven']);
        $onder = $H - max($m, $f['veilig_onder']);
        $donker = $soort !== 'licht';
        $kop = $this->stijl('kop', $formaat);
        $logoBreedte = (int) round(280 * $f['schaal']);
        $logoY = $onder - (int) round($logoBreedte / 6.274);

        $lagen = match ($soort) {
            'verloop' => [$this->verloop(0, 0, $W, $H, 0, merk: true, naam: 'Merkverloop')],
            default => [$this->verloop(0, 0, $W, 24, 0, merk: true, naam: 'Merkbalk')],
        };
        $tekstY = $boven + (int) round(40 * $f['schaal']);
        $lagen[] = $this->tekst('Jouw kop in twee regels', 'kop', $formaat, $m, $tekstY, $W - 2 * $m, $donker ? '#FFFFFF' : '#3A3659', vak: 'Kop');
        $lagen[] = $this->tekst('Een korte tekst die uitlegt wat je wilt zeggen.', 'tekst', $formaat, $m, $tekstY + (int) round($kop['grootte'] * $kop['regelafstand'] * 2) + 30, $W - 2 * $m,
            $donker ? '#F6F5F9' : '#6D6794', vak: 'Tekst');
        $lagen[] = $this->logo($donker ? 'wit' : 'aubergine', $m, $logoY, $logoBreedte);

        $pagina = ['achtergrond' => ['licht' => '#F6F5F9', 'donker' => '#2E2B48', 'verloop' => '#3A3659'][$soort] ?? '#F6F5F9', 'lagen' => $lagen];
        $aantal = $formaat === 'carrousel' ? 3 : 1;

        return array_map(fn ($i) => $i === 0 ? $pagina : ['achtergrond' => $pagina['achtergrond'], 'lagen' => array_map(fn ($l) => ['id' => $l['id'].'-'.($i + 1)] + $l, $lagen)], range(0, $aantal - 1));
    }

    // Bouwstenen van lagen.

    private function basisLaag(string $soort, string $naam, float $x, float $y, float $w, float $h): array
    {
        return ['id' => $soort.'-'.Str::lower(Str::random(4)).(++$this->teller), 'soort' => $soort, 'naam' => $naam, 'x' => $x, 'y' => $y,
            'breedte' => $w, 'hoogte' => $h, 'rotatie' => 0, 'zichtbaar' => true, 'vergrendeld' => false];
    }

    public function tekst(string $tekst, string $stijl, string $formaat, float $x, float $y, float $w, string $kleur, string $uitlijning = 'left', ?string $vak = null, ?string $naam = null): array
    {
        $s = $this->stijl($stijl, $formaat);

        return $this->basisLaag('tekst', $vak ?? $naam ?? 'Tekst', $x, $y, $w, round($s['grootte'] * $s['regelafstand'])) + $s + [
            'tekst' => $tekst, 'stijl' => $stijl, 'kleur' => $kleur, 'uitlijning' => $uitlijning, 'vak' => $vak !== null, 'vakNaam' => $vak,
        ];
    }

    /** Foto- of videovak: het kader staat vast, alleen de inhoud verschuift en zoomt. */
    public function vak(string $soort, string $naam, float $x, float $y, float $w, float $h, float $hoek): array
    {
        return ['soort' => $soort] + $this->basisLaag($soort, $naam, $x, $y, $w, $h) + ['bron' => null, 'uitsnede' => ['x' => 0.5, 'y' => 0.5, 'zoom' => 1],
            'hoekafronding' => $hoek, 'vak' => true, 'vakNaam' => $naam, 'vergrendeld' => true];
    }

    public function vorm(string $vorm, float $x, float $y, float $w, float $h, string $kleur, float $hoekafronding = 0, float $dekking = 1): array
    {
        return $this->basisLaag('vorm', $vorm === 'cirkel' ? 'Cirkel' : 'Rechthoek', $x, $y, $w, $h) + [
            'vorm' => $vorm, 'vulling' => $kleur, 'hoekafronding' => $hoekafronding, 'dekking' => $dekking,
        ];
    }

    /** Merkverloop; als merkelement (merkbalk, achtergrond) vergrendeld. */
    public function verloop(float $x, float $y, float $w, float $h, float $hoek, bool $merk = false, string $naam = 'Merkverloop'): array
    {
        $v = $this->huidig()['verloop'];
        $laag = $this->basisLaag('verloop', $naam, $x, $y, $w, $h) + ['van' => $v['van'], 'naar' => $v['naar'], 'hoek' => $v['hoek'], 'hoekafronding' => $hoek];

        return $merk ? array_merge($laag, ['merk' => true, 'vergrendeld' => true]) : $laag;
    }

    /** Logo (merkelement, vergrendeld). De woordmerken zijn 627,4 bij 100. */
    public function logo(string $variant, float $x, float $y, float $w): array
    {
        $bestand = $this->huidig()['logos'][$variant] ?? $this->huidig()['logos']['wit'];

        return array_merge($this->basisLaag('logo', 'Logo', $x, $y, $w, round($w / 6.274)), [
            'bron' => ['soort' => 'logo', 'naam' => $bestand], 'merk' => true, 'vergrendeld' => true,
        ]);
    }

    /** Voor de schermen: alles wat de editor, controle en het Merkkit-scherm nodig hebben. */
    public function voorScherm(): array
    {
        $kit = $this->huidig();
        $kit['formaten'] = collect(config('studio.formaten'))->map(fn ($f, $k) => $f + ($kit['formaten'][$k] ?? []))->all();
        $kit['basissen'] = self::BASISSEN;

        return $kit;
    }
}
