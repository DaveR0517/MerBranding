<?php

namespace App\Http\Controllers\Team\Studio;

use App\Jobs\ZetStudioVideoOm;
use App\Models\ActivityLog;
use App\Models\StudioDesign;
use App\Models\StudioTemplate;
use App\Services\Studio\Merkkit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** MeStudio, Ontwerpen: overzicht, nieuw ontwerp (formaat of sjabloon), de editor en opslaan als geheel. */
class OntwerpenController extends StudioController
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'formaat' => ['nullable', Rule::in(array_keys($this->formaten()))],
            'status' => ['nullable', Rule::in(StudioDesign::STATUSSEN)],
        ]);

        $ontwerpen = StudioDesign::with('user')
            ->when($filters['formaat'] ?? null, fn ($q, $f) => $q->where('format', $f))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest('updated_at')->limit(60)->get();

        return Inertia::render('team/studio/ontwerpen', [
            'ontwerpen' => $ontwerpen->map(fn ($d) => $this->ontwerp($d)),
            'filters' => ['formaat' => $filters['formaat'] ?? null, 'status' => $filters['status'] ?? null],
            'formaten' => $this->formaten(),
            'basissen' => Merkkit::BASISSEN,
            'statussen' => config('studio.statussen'),
            'sjablonen' => StudioTemplate::actief()->orderByDesc('is_builtin')->orderBy('name')->get()->map(fn ($s) => $this->sjabloon($s)),
            'totaal' => StudioDesign::count(),
        ]);
    }

    /** Nieuw ontwerp: nooit blanco. Vanaf een merkbasis (licht, donker, verloop) in een formaat, of als kopie van een sjabloon. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'formaat' => ['required_without:sjabloon', 'nullable', Rule::in(array_keys($this->formaten()))],
            'basis' => ['required_without:sjabloon', 'nullable', Rule::in(array_keys(Merkkit::BASISSEN))],
            'sjabloon' => ['nullable', 'integer', 'exists:studio_templates,id'],
        ]);

        $ontwerp = isset($data['sjabloon'])
            ? $this->nieuwVanSjabloon(StudioTemplate::findOrFail($data['sjabloon']), $request)
            : StudioDesign::create([
                'user_id' => $request->user()->id, 'name' => 'Nieuw ontwerp, '.$this->formaten()[$data['formaat']]['naam'], 'format' => $data['formaat'],
                'width' => $this->formaten()[$data['formaat']]['breedte'], 'height' => $this->formaten()[$data['formaat']]['hoogte'], 'status' => 'concept',
                'pages' => $this->merkkit()->basis($data['formaat'], $data['basis']),
            ]);

        return redirect()->route('team.studio.ontwerpen.bewerken', $ontwerp);
    }

    public function bewerken(StudioDesign $ontwerp): Response
    {
        $ontwerp->load('user', 'exports');

        return Inertia::render('team/studio/editor', [
            'soort' => 'ontwerp',
            'item' => $this->ontwerp($ontwerp, true),
            'exports' => $ontwerp->exports->map(fn ($e) => ExportController::weergave($e)),
            'ffmpeg' => ZetStudioVideoOm::ffmpeg() !== null,
            'maxVideoSeconden' => config('studio.max_video_seconden'),
            'maxUploadMb' => config('studio.max_upload_mb'),
            ...$this->bronnen(),
        ]);
    }

    /** Opslaan als geheel: lagen als JSON per pagina, naam en een kleine voorvertoning. */
    public function opslaan(Request $request, StudioDesign $ontwerp): JsonResponse|RedirectResponse
    {
        // validate() houdt alleen gecontroleerde sleutels over; de lagen zelf komen ongewijzigd uit de invoer.
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], ...$this->paginaRegels()]);
        if ($ontwerp->format !== 'carrousel') {
            abort_if(count($data['pages']) > 1, 422, 'Alleen een carrousel heeft meerdere pagina\'s.');
        }

        $this->bewaakMerklagen($ontwerp->pages ?? [], $request->input('pages'), $request);
        $ontwerp->fill(['name' => $data['name'], 'pages' => $this->merkkit()->pasToe($request->input('pages'), $ontwerp->format)]);
        if ($pad = $this->bewaarVoorvertoning($data['voorvertoning'] ?? null, "ontwerp-{$ontwerp->id}")) {
            $ontwerp->preview_path = $pad;
        }
        $ontwerp->touch();
        $ontwerp->save();

        return $request->wantsJson()
            ? response()->json(['ok' => true, 'opgeslagen_op' => $ontwerp->updated_at->toIso8601String(), 'heeft_video' => $ontwerp->heeftVideo()])
            : back()->with('melding', 'Ontwerp opgeslagen.');
    }

    public function dupliceren(Request $request, StudioDesign $ontwerp): RedirectResponse
    {
        $kopie = $ontwerp->replicate(['preview_path', 'feed_position', 'post_url', 'posted_at', 'scheduled_at']);
        $kopie->fill(['name' => $ontwerp->name.' (kopie)', 'status' => 'concept', 'user_id' => $request->user()->id])->save();

        return redirect()->route('team.studio.ontwerpen.bewerken', $kopie)->with('melding', 'Kopie gemaakt.');
    }

    public function verwijderen(StudioDesign $ontwerp): RedirectResponse
    {
        $disk = Storage::disk('local');
        foreach ($ontwerp->exports as $export) {
            $disk->delete($export->path);
        }
        if ($ontwerp->preview_path) {
            $disk->delete($ontwerp->preview_path);
        }
        $ontwerp->delete();

        return redirect()->route('team.studio.ontwerpen.index')->with('melding', 'Ontwerp verwijderd.');
    }

    /** Eigen ontwerp bewaren als sjabloon voor het hele team. */
    public function alsSjabloon(Request $request, StudioDesign $ontwerp): RedirectResponse
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:120']]);
        $sjabloon = StudioTemplate::create([
            'user_id' => $request->user()->id, 'name' => $data['name'] ?? $ontwerp->name, 'format' => $ontwerp->format,
            'width' => $ontwerp->width, 'height' => $ontwerp->height, 'pages' => $ontwerp->pages,
        ]);

        return redirect()->route('team.studio.sjablonen.index')->with('melding', "Sjabloon \"{$sjabloon->name}\" opgeslagen.");
    }

    /** Super Admin exporteert ondanks een rode merkcontrole: alleen met reden, altijd in het logboek. */
    public function merkcontrole(Request $request, StudioDesign $ontwerp): JsonResponse
    {
        abort_unless($request->user()->hasRole('Super Admin'), 403, 'Alleen Super Admin kan exporteren ondanks de merkcontrole.');
        $data = $request->validate(['reden' => ['required', 'string', 'min:5', 'max:500'], 'bevindingen' => ['array', 'max:50'], 'bevindingen.*' => ['string', 'max:300']]);

        ActivityLog::create([
            'user_id' => $request->user()->id, 'subject_type' => StudioDesign::class, 'subject_id' => $ontwerp->id,
            'event' => 'merkcontrole_overgeslagen', 'reason' => $data['reden'], 'data' => ['bevindingen' => $data['bevindingen'] ?? []],
        ]);

        return response()->json(['ok' => true]);
    }

    public function voorvertoning(StudioDesign $ontwerp): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        abort_unless($ontwerp->preview_path && $disk->exists($ontwerp->preview_path), 404);

        return response()->file($disk->path($ontwerp->preview_path), ['Cache-Control' => 'private, max-age=86400']);
    }
}
