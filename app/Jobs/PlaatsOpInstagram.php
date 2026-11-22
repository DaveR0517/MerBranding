<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\StudioDesign;
use App\Models\StudioExport;
use App\Models\User;
use App\Services\Kanalen\EigenKanalen;
use App\Services\Koppelingen\InstagramPlatform;
use App\Services\Melder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\URL;

/**
 * Een ontwerp uit MeStudio plaatsen op het eigen Instagram-account (Content Publishing API).
 * Meta haalt het bestand zelf op: daarom een tijdelijke ondertekende publieke link (1 uur) naar de export.
 * Stappen: container maken, wachten tot Meta klaar is met verwerken, publiceren, link bewaren in de feedplanner.
 */
class PlaatsOpInstagram implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $ontwerpId, public int $userId) {}

    public function handle(EigenKanalen $kanalen, Melder $melder): void
    {
        $ontwerp = StudioDesign::findOrFail($this->ontwerpId);
        $door = User::find($this->userId);
        $uitkomst = $this->plaats($ontwerp, $kanalen);

        ActivityLog::create([
            'user_id' => $door?->id, 'subject_type' => $ontwerp->getMorphClass(), 'subject_id' => $ontwerp->id,
            'event' => $uitkomst === null ? 'instagram_geplaatst' : 'instagram_plaatsen_mislukt', 'reason' => $uitkomst, 'data' => ['post_url' => $ontwerp->post_url],
        ]);
        $melder->stuur('t-kanaal-plaatsen', $door, [
            'ontwerp' => $ontwerp->name, 'uitkomst' => $uitkomst === null ? 'staat op Instagram' : "is niet geplaatst: {$uitkomst}",
        ], route('team.studio.feedplanner.index'));
    }

    /** Plaatsen; geeft null bij succes, anders de reden. */
    private function plaats(StudioDesign $ontwerp, EigenKanalen $kanalen): ?string
    {
        $account = $kanalen->actief('instagram');
        $ig = $kanalen->platform('instagram');
        if (! $account || ! $ig instanceof InstagramPlatform || ! $kanalen->magPlaatsen()) {
            return 'Instagram is niet gekoppeld met het recht om te plaatsen.';
        }

        $bestanden = self::bestanden($ontwerp);
        if (is_string($bestanden)) {
            return $bestanden;
        }

        $link = fn (StudioExport $e) => URL::temporarySignedRoute('kanalen.bestand', now()->addMinutes((int) config('kanalen.plaatsen_url_minuten')), ['export' => $e->id]);
        $video = $bestanden[0]->kind === 'mp4';
        $caption = (string) $ontwerp->caption;

        if (count($bestanden) > 1) {
            $kinderen = [];
            foreach ($bestanden as $e) {
                $kind = $ig->maakContainer($account, ['image_url' => $link($e), 'is_carousel_item' => 'true']);
                if (! $kind->isGelukt()) {
                    return $kind->melding;
                }
                $kinderen[] = $kind->data['id'];
            }
            $velden = ['media_type' => 'CAROUSEL', 'children' => implode(',', $kinderen), 'caption' => $caption];
        } elseif ($video) {
            $velden = ['media_type' => 'REELS', 'video_url' => $link($bestanden[0]), 'caption' => $caption, 'share_to_feed' => 'true'];
        } elseif ($ontwerp->format === 'story') {
            $velden = ['media_type' => 'STORIES', 'image_url' => $link($bestanden[0])];
        } else {
            $velden = ['image_url' => $link($bestanden[0]), 'caption' => $caption];
        }

        $container = $ig->maakContainer($account, $velden);
        if (! $container->isGelukt()) {
            return $container->melding;
        }

        // Video's verwerkt Meta op de achtergrond: wachten tot FINISHED.
        for ($i = 0; $i < (int) config('kanalen.plaatsen_pogingen'); $i++) {
            $stand = $ig->containerStatus($account, $container->data['id']);
            $code = $stand->data['status'] ?? null;
            if ($code === 'FINISHED') {
                break;
            }
            if (! $stand->isGelukt() || in_array($code, ['ERROR', 'EXPIRED'], true)) {
                return 'Instagram kon het bestand niet verwerken'.($stand->data['toelichting'] ?? null ? ': '.$stand->data['toelichting'] : '.');
            }
            sleep((int) config('kanalen.plaatsen_wacht_seconden'));
        }

        $geplaatst = $ig->publiceer($account, $container->data['id']);
        if (! $geplaatst->isGelukt()) {
            return $geplaatst->melding;
        }

        $ontwerp->update(['status' => 'geplaatst', 'posted_at' => now(), 'post_url' => $geplaatst->data['permalink'] ?? $ontwerp->post_url]);

        return null;
    }

    /**
     * De exports die geplaatst worden, of de reden waarom het niet kan.
     * Instagram neemt alleen JPEG-afbeeldingen en MP4-video's.
     *
     * @return list<StudioExport>|string
     */
    public static function bestanden(StudioDesign $ontwerp): array|string
    {
        if (! in_array($ontwerp->format, ['instagram_post', 'instagram_portret', 'story', 'carrousel'], true)) {
            return 'Dit formaat is niet voor Instagram.';
        }
        $klaar = $ontwerp->exports()->where('status', 'klaar')->get();

        if ($ontwerp->format === 'carrousel') {
            $paginas = $klaar->where('kind', 'jpg')->unique('page')->sortBy('page')->values();

            return $paginas->count() >= 2 ? $paginas->take(10)->all() : 'Exporteer de pagina\'s van de carrousel als JPG.';
        }

        $bestand = $klaar->firstWhere('kind', 'mp4') ?? $klaar->firstWhere('kind', 'jpg');

        return $bestand ? [$bestand] : 'Exporteer het ontwerp als JPG of MP4. Instagram neemt geen PNG.';
    }
}
