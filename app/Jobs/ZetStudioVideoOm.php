<?php

namespace App\Jobs;

use App\Models\StudioExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;

/**
 * MeStudio: zet een in de browser opgenomen video (webm) om naar MP4 (H.264/AAC, geschikt voor Instagram).
 * Zonder ffmpeg blijft de webm bewaard; het scherm zegt dat eerlijk (status alleen_webm).
 */
class ZetStudioVideoOm implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public StudioExport $export) {}

    /** Volledig pad naar ffmpeg, of null als het niet aanwezig is. */
    public static function ffmpeg(): ?string
    {
        $ingesteld = (string) config('studio.ffmpeg');
        if ($ingesteld === '') {
            return null;
        }
        if (str_contains($ingesteld, '/')) {
            return is_executable($ingesteld) ? $ingesteld : null;
        }

        return (new ExecutableFinder)->find($ingesteld);
    }

    public function handle(): void
    {
        $bron = $this->export;
        $ffmpeg = self::ffmpeg();
        if (! $ffmpeg) {
            $bron->update(['status' => 'alleen_webm', 'message' => 'ffmpeg ontbreekt op de server: de video staat als webm klaar. Instagram accepteert webm niet altijd.']);

            return;
        }

        $disk = Storage::disk('local');
        $doelPad = preg_replace('/\.[^.]+$/', '', $bron->path).'.mp4';
        $resultaat = Process::timeout($this->timeout)->run([
            $ffmpeg, '-y', '-i', $disk->path($bron->path),
            '-c:v', 'libx264', '-profile:v', 'high', '-pix_fmt', 'yuv420p', '-preset', 'veryfast', '-crf', '20', '-r', '30',
            '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2',
            '-c:a', 'aac', '-b:a', '128k', '-ar', '44100', '-movflags', '+faststart',
            $disk->path($doelPad),
        ]);

        if (! $resultaat->successful() || ! $disk->exists($doelPad)) {
            $bron->update(['status' => 'mislukt', 'message' => 'Omzetten naar MP4 is mislukt. De webm staat nog klaar.']);
            report(new \RuntimeException('MeStudio ffmpeg: '.str($resultaat->errorOutput())->limit(500)));

            return;
        }

        StudioExport::create([
            'design_id' => $bron->design_id, 'user_id' => $bron->user_id, 'kind' => 'mp4', 'page' => $bron->page ?? 1,
            'path' => $doelPad, 'mime' => 'video/mp4', 'size' => $disk->size($doelPad), 'status' => 'klaar',
        ]);
        $bron->update(['status' => 'klaar', 'message' => 'Omgezet naar MP4.']);
    }
}
