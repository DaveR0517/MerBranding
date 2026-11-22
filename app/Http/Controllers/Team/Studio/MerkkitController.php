<?php

namespace App\Http\Controllers\Team\Studio;

use App\Models\ActivityLog;
use App\Services\Studio\Merkkit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MeStudio, Merkkit: iedereen in de studio ziet de huisstijl; alleen Super Admin past hem aan. */
class MerkkitController extends StudioController
{
    public function index(Request $request): Response
    {
        return Inertia::render('team/studio/merkkit', [
            'merkkit' => $this->merkkit()->voorScherm(),
            'standaard' => $this->merkkit()->standaard(),
            'logoBestanden' => collect(self::logos())->pluck('naam'),
            'magBewerken' => $request->user()->hasRole('Super Admin'),
            'wijzigingen' => ActivityLog::with('user')->where('event', 'merkkit_gewijzigd')->latest('id')->limit(10)->get()
                ->map(fn ($l) => ['op' => $l->created_at?->toIso8601String(), 'door' => $l->user?->name, 'aantal' => count($l->data['wijzigingen'] ?? []),
                    'velden' => array_slice(array_keys($l->data['wijzigingen'] ?? []), 0, 6)]),
        ]);
    }

    public function opslaan(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Super Admin'), 403, 'Alleen Super Admin past de merkkit aan.');
        $hex = 'regex:/^#[0-9A-Fa-f]{6}$/';
        $logos = collect(self::logos())->pluck('naam')->implode(',');

        $data = $request->validate([
            'palet' => ['required', 'array', 'min:2', 'max:12'],
            'palet.*.kleur' => ['required', $hex],
            'palet.*.naam' => ['required', 'string', 'max:40'],
            'verloop.van' => ['required', $hex],
            'verloop.naar' => ['required', $hex],
            'verloop.hoek' => ['required', 'integer', 'between:0,360'],
            'stijlen' => ['required', 'array'],
            ...collect(Merkkit::STIJLEN)->flatMap(fn ($s) => [
                "stijlen.{$s}.naam" => ['required', 'string', 'max:30'],
                "stijlen.{$s}.lettertype" => ['required', 'in:'.implode(',', config('studio.merkkit.lettertypes'))],
                "stijlen.{$s}.grootte" => ['required', 'integer', 'between:12,400'],
                "stijlen.{$s}.gewicht" => ['required', 'integer', 'in:400,500,600,700,800,900'],
                "stijlen.{$s}.regelafstand" => ['required', 'numeric', 'between:0.8,2.5'],
                "stijlen.{$s}.hoofdletters" => ['required', 'boolean'],
            ])->all(),
            'logos.wit' => ['required', 'in:'.$logos],
            'logos.aubergine' => ['required', 'in:'.$logos],
            'logos.verloop' => ['required', 'in:'.$logos],
            ...collect(array_keys(config('studio.formaten')))->flatMap(fn ($f) => [
                "formaten.{$f}.marge" => ['required', 'integer', 'between:0,300'],
                "formaten.{$f}.veilig_boven" => ['required', 'integer', 'between:0,600'],
                "formaten.{$f}.veilig_onder" => ['required', 'integer', 'between:0,600'],
                "formaten.{$f}.schaal" => ['required', 'numeric', 'between:0.5,2'],
            ])->all(),
            'raster' => ['required', 'integer', 'between:1,100'],
            'controle.min_tekst' => ['required', 'integer', 'between:12,80'],
            'controle.contrast_klein' => ['required', 'numeric', 'between:1,21'],
            'controle.contrast_groot' => ['required', 'numeric', 'between:1,21'],
            'controle.groot_vanaf_scherm_px' => ['required', 'integer', 'between:10,60'],
            'controle.schermbreedte' => ['required', 'integer', 'between:300,1080'],
            'controle.logo_min_breedte' => ['required', 'integer', 'between:40,800'],
            'controle.max_stijlen' => ['required', 'integer', 'between:1,5'],
        ]);

        // Alleen de bekende sleutels bewaren (formaten zonder naam en maat: die liggen vast in config).
        $data['formaten'] = collect($data['formaten'])->map(fn ($f) => array_intersect_key($f, array_flip(['marge', 'veilig_boven', 'veilig_onder', 'schaal'])))->all();
        $aantal = $this->merkkit()->opslaan($data, $request->user());

        return back()->with('melding', $aantal ? "Merkkit opgeslagen ({$aantal} wijzigingen, staat in het logboek)." : 'Niets gewijzigd.');
    }
}
