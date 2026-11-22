<?php

namespace App\Http\Controllers\Team\Studio;

use App\Models\StudioMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** MeStudio, Mediatheek: foto's, video's en logo's voor de studio, plus goedgekeurde video's van creators. */
class MediatheekController extends StudioController
{
    public function index(): Response
    {
        return Inertia::render('team/studio/mediatheek', [
            'media' => StudioMedia::with('user')->latest('id')->limit(300)->get()->map(fn ($m) => $this->media($m)),
            'creatorVideos' => $this->creatorVideos(),
            'maxUploadMb' => config('studio.max_upload_mb'),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'soort' => ['nullable', Rule::in(StudioMedia::SOORTEN)],
            'bestand' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,svg,mp4,mov,webm', 'max:'.(config('studio.max_upload_mb') * 1024)],
        ], ['bestand.max' => 'Dit bestand is groter dan '.config('studio.max_upload_mb').' MB.']);

        $media = self::bewaar($request->file('bestand'), $data['soort'] ?? null, $request->user()->id);

        return $request->wantsJson()
            ? response()->json(['media' => $this->media($media->load('user'))])
            : back()->with('melding', "\"{$media->name}\" staat in de mediatheek.");
    }

    /** Bewaar een upload privé onder studio/media; de soort volgt uit het bestand als die niet is meegegeven. */
    public static function bewaar(UploadedFile $bestand, ?string $soort, int $userId): StudioMedia
    {
        $mime = (string) $bestand->getMimeType();
        $soort = match (true) {
            str_starts_with($mime, 'video/') => 'video',
            $soort === 'logo' || str_contains($mime, 'svg') => 'logo',
            default => 'foto',
        };
        $extensie = strtolower($bestand->getClientOriginalExtension() ?: $bestand->extension());
        $pad = $bestand->storeAs('studio/media', Str::uuid().'.'.$extensie, 'local');
        [$breedte, $hoogte] = @getimagesize($bestand->getRealPath()) ?: [null, null];

        return StudioMedia::create([
            'user_id' => $userId, 'kind' => $soort, 'name' => Str::limit($bestand->getClientOriginalName(), 120, ''),
            'disk' => 'local', 'path' => $pad, 'mime' => $mime, 'size' => $bestand->getSize(),
            'width' => $breedte, 'height' => $hoogte,
        ]);
    }

    /** Beveiligd serveren (privé-disk). Ondersteunt spoelen in video's. Svg zonder scripts. */
    public function bestand(StudioMedia $media): BinaryFileResponse
    {
        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->path), 404);

        return response()->file($disk->path($media->path), [
            'Content-Type' => $media->mime ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:",
        ]);
    }

    public function verwijderen(StudioMedia $media): RedirectResponse
    {
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        return back()->with('melding', 'Bestand verwijderd. Ontwerpen die het gebruikten tonen nu een leeg vak.');
    }
}
