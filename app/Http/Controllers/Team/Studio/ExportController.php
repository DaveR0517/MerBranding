<?php

namespace App\Http\Controllers\Team\Studio;

use App\Jobs\ZetStudioVideoOm;
use App\Models\StudioDesign;
use App\Models\StudioExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * MeStudio, exports: de browser maakt de afbeelding of video, de server bewaart hem privé onder studio/exports
 * en zet een video met ffmpeg om naar MP4 (job op de queue). Downloaden alleen via deze beveiligde routes.
 */
class ExportController extends StudioController
{
    private const SOORTEN = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webm' => 'video/webm', 'mp4' => 'video/mp4'];

    public static function weergave(StudioExport $e): array
    {
        return [
            'id' => $e->id, 'kind' => $e->kind, 'page' => $e->page, 'size' => $e->size, 'status' => $e->status,
            'message' => $e->message, 'created_at' => $e->created_at?->toIso8601String(),
            'url' => route('team.studio.exports.download', $e),
        ];
    }

    public function store(Request $request, StudioDesign $ontwerp): JsonResponse
    {
        $data = $request->validate([
            'soort' => ['required', 'in:'.implode(',', array_keys(self::SOORTEN))],
            'pagina' => ['nullable', 'integer', 'min:1', 'max:10'],
            'bestand' => ['required', 'file', 'max:'.(config('studio.max_upload_mb') * 1024)],
        ]);
        $soort = $data['soort'];
        $mime = (string) $request->file('bestand')->getMimeType();
        $isVideo = in_array($soort, ['webm', 'mp4'], true);
        abort_unless($isVideo ? str_starts_with($mime, 'video/') || $mime === 'application/octet-stream' : str_starts_with($mime, 'image/'), 422, 'Dit bestand past niet bij de gekozen soort.');

        $pad = $request->file('bestand')->storeAs("studio/exports/{$ontwerp->id}", Str::slug($ontwerp->name).'-'.($data['pagina'] ?? 1).'-'.Str::random(6).'.'.$soort, 'local');
        $export = StudioExport::create([
            'design_id' => $ontwerp->id, 'user_id' => $request->user()->id, 'kind' => $soort, 'page' => $data['pagina'] ?? 1,
            'path' => $pad, 'mime' => self::SOORTEN[$soort], 'size' => $request->file('bestand')->getSize(),
            'status' => $soort === 'webm' ? 'omzetten' : 'klaar',
        ]);

        if ($soort === 'webm') {
            ZetStudioVideoOm::dispatch($export);
        }

        // Draait de queue direct (sync), dan is de MP4 er al: geef die terug om te downloaden.
        $klaar = $ontwerp->exports()->where('kind', 'mp4')->where('id', '>', $export->id)->first() ?? $export->fresh();

        return response()->json(['export' => self::weergave($klaar), 'exports' => $ontwerp->exports()->get()->map(fn ($e) => self::weergave($e))]);
    }

    /** Stand van de exports (de editor vraagt dit na een video op tot de MP4 klaar is). */
    public function lijst(StudioDesign $ontwerp): JsonResponse
    {
        return response()->json(['exports' => $ontwerp->exports()->get()->map(fn ($e) => self::weergave($e))]);
    }

    public function download(StudioExport $export): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($export->path), 404, 'Dit bestand is niet meer beschikbaar.');
        $naam = Str::slug($export->design->name).($export->design->format === 'carrousel' ? "-{$export->page}" : '').'.'.$export->kind;

        return response()->download($disk->path($export->path), $naam, ['Content-Type' => $export->mime]);
    }

    /** Carrousel: de laatste afbeelding per pagina samen in één zip. */
    public function zip(StudioDesign $ontwerp): BinaryFileResponse
    {
        $perPagina = $ontwerp->exports()->whereIn('kind', ['png', 'jpg'])->get()->unique('page')->sortBy('page');
        abort_if($perPagina->isEmpty(), 404, 'Exporteer eerst de pagina\'s als afbeelding.');

        $disk = Storage::disk('local');
        $tijdelijk = tempnam(sys_get_temp_dir(), 'studio-zip');
        $zip = new ZipArchive;
        $zip->open($tijdelijk, ZipArchive::OVERWRITE);
        foreach ($perPagina as $e) {
            if ($disk->exists($e->path)) {
                $zip->addFile($disk->path($e->path), sprintf('%02d-%s.%s', $e->page, Str::slug($ontwerp->name), $e->kind));
            }
        }
        $zip->close();

        return response()->download($tijdelijk, Str::slug($ontwerp->name).'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
