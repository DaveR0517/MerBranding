<?php

namespace App\Http\Controllers\Team\Studio;

use App\Jobs\ZetStudioVideoOm;
use App\Models\StudioMedia;
use App\Models\StudioTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** MeStudio, Sjablonen: huisstijlsjablonen en eigen sjablonen met vakken voor foto, video en tekst. */
class SjablonenController extends StudioController
{
    public function index(Request $request): Response
    {
        $archief = $request->boolean('archief');

        return Inertia::render('team/studio/sjablonen', [
            'sjablonen' => StudioTemplate::with('user')
                ->when($archief, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
                ->orderByDesc('is_builtin')->orderBy('name')->get()->map(fn ($s) => $this->sjabloon($s)),
            'archief' => $archief,
            'aantalGearchiveerd' => StudioTemplate::whereNotNull('archived_at')->count(),
            'formaten' => $this->formaten(),
            'fotos' => StudioMedia::whereIn('kind', ['foto'])->latest('id')->limit(60)->get()->map(fn ($m) => $this->media($m)),
            'maxUploadMb' => config('studio.max_upload_mb'),
        ]);
    }

    public function gebruiken(Request $request, StudioTemplate $sjabloon): RedirectResponse
    {
        return redirect()->route('team.studio.ontwerpen.bewerken', $this->nieuwVanSjabloon($sjabloon, $request));
    }

    public function bewerken(StudioTemplate $sjabloon): Response
    {
        return Inertia::render('team/studio/editor', [
            'soort' => 'sjabloon',
            'item' => $this->sjabloon($sjabloon) + ['status' => null],
            'exports' => [],
            'ffmpeg' => ZetStudioVideoOm::ffmpeg() !== null,
            'maxVideoSeconden' => config('studio.max_video_seconden'),
            'maxUploadMb' => config('studio.max_upload_mb'),
            ...$this->bronnen(),
        ]);
    }

    public function opslaan(Request $request, StudioTemplate $sjabloon): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], ...$this->paginaRegels()]);
        $this->bewaakMerklagen($sjabloon->pages ?? [], $request->input('pages'), $request);
        $sjabloon->fill(['name' => $data['name'], 'pages' => $this->merkkit()->pasToe($request->input('pages'), $sjabloon->format)]);
        if ($pad = $this->bewaarVoorvertoning($data['voorvertoning'] ?? null, "sjabloon-{$sjabloon->id}")) {
            $sjabloon->preview_path = $pad;
        }
        $sjabloon->save();

        return $request->wantsJson()
            ? response()->json(['ok' => true, 'opgeslagen_op' => $sjabloon->updated_at->toIso8601String()])
            : back()->with('melding', 'Sjabloon opgeslagen.');
    }

    public function dupliceren(Request $request, StudioTemplate $sjabloon): RedirectResponse
    {
        $kopie = $sjabloon->replicate(['slug', 'preview_path', 'archived_at']);
        $kopie->fill(['name' => $sjabloon->name.' (kopie)', 'is_builtin' => false, 'user_id' => $request->user()->id])->save();

        return back()->with('melding', "Kopie \"{$kopie->name}\" gemaakt.");
    }

    public function hernoemen(Request $request, StudioTemplate $sjabloon): RedirectResponse
    {
        $sjabloon->update($request->validate(['name' => ['required', 'string', 'max:120']]));

        return back()->with('melding', 'Naam aangepast.');
    }

    /** Archiveren of terugzetten. Een gearchiveerd sjabloon is niet meer te gebruiken; bestaande ontwerpen blijven. */
    public function archiveren(StudioTemplate $sjabloon): RedirectResponse
    {
        $sjabloon->update(['archived_at' => $sjabloon->archived_at ? null : now()]);

        return back()->with('melding', $sjabloon->archived_at ? 'Sjabloon gearchiveerd.' : 'Sjabloon teruggezet.');
    }

    /**
     * Sjabloon vanaf een eigen achtergrond (bijvoorbeeld een bestaande Instagram-kit): de afbeelding ligt
     * vergrendeld onderin, daarna zet je er vakken op in de editor.
     */
    public function vanAchtergrond(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'formaat' => ['required', Rule::in(array_keys($this->formaten()))],
            'name' => ['nullable', 'string', 'max:120'],
            'media' => ['required_without:bestand', 'nullable', 'integer', 'exists:studio_media,id'],
            'bestand' => ['required_without:media', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.(config('studio.max_upload_mb') * 1024)],
        ]);

        $media = isset($data['media'])
            ? StudioMedia::findOrFail($data['media'])
            : MediatheekController::bewaar($request->file('bestand'), 'foto', $request->user()->id);
        $f = $this->formaten()[$data['formaat']];
        $achtergrond = [
            'id' => 'achtergrond-'.Str::random(6), 'soort' => 'foto', 'naam' => 'Achtergrond', 'x' => 0, 'y' => 0,
            'breedte' => $f['breedte'], 'hoogte' => $f['hoogte'], 'rotatie' => 0, 'zichtbaar' => true, 'vergrendeld' => true,
            'bron' => ['soort' => 'media', 'id' => $media->id], 'uitsnede' => ['x' => 0.5, 'y' => 0.5, 'zoom' => 1],
        ];

        $sjabloon = StudioTemplate::create([
            'user_id' => $request->user()->id, 'name' => $data['name'] ?? 'Eigen sjabloon, '.$f['naam'], 'format' => $data['formaat'],
            'width' => $f['breedte'], 'height' => $f['hoogte'],
            'pages' => array_fill(0, $data['formaat'] === 'carrousel' ? 3 : 1, ['achtergrond' => '#FFFFFF', 'lagen' => [$achtergrond]]),
        ]);

        return redirect()->route('team.studio.sjablonen.bewerken', $sjabloon)->with('melding', 'Zet nu vakken op je achtergrond: voeg een fotovak of tekstvak toe.');
    }
}
