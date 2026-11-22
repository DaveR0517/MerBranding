<?php

namespace App\Http\Controllers\Team\Kanalen;

use App\Http\Controllers\Controller;
use App\Models\StudioExport;
use App\Services\Kanalen\Webhooks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Publieke adressen voor Meta en TikTok (zonder inloggen, zonder CSRF; routes/portaal-webhooks.php).
 * Elke melding wordt eerst op de handtekening gecontroleerd.
 */
class WebhookController extends Controller
{
    /** Meta controleert het adres één keer met hub.mode, hub.verify_token en hub.challenge. */
    public function instagramVerifieer(Request $request): Response
    {
        $token = (string) config('services.instagram.webhook_verify_token');
        $klopt = $token !== '' && $request->query('hub_mode') === 'subscribe' && hash_equals($token, (string) $request->query('hub_verify_token'));

        return $klopt ? response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']) : response('Verificatie mislukt', 403);
    }

    public function instagram(Request $request, Webhooks $w): Response
    {
        if (! $w->metaHandtekeningKlopt($request->getContent(), $request->header('X-Hub-Signature-256'))) {
            Log::warning('Webhook Instagram: handtekening klopt niet');

            return response('Ongeldige handtekening', 403);
        }
        $w->instagram((array) $request->json()->all());

        return response('EVENT_RECEIVED', 200);
    }

    public function tiktok(Request $request, Webhooks $w): Response
    {
        if (! $w->tiktokHandtekeningKlopt($request->getContent(), $request->header('TikTok-Signature'))) {
            Log::warning('Webhook TikTok: handtekening klopt niet');

            return response('Ongeldige handtekening', 403);
        }
        $w->tiktok((array) $request->json()->all());

        return response('ok', 200);
    }

    /** Meta: iemand heeft de app weggehaald (Deauthorize callback). */
    public function instagramOntkoppeld(Request $request, Webhooks $w): Response
    {
        $data = $w->metaSignedRequest($request->input('signed_request'));
        if (! $data || ! isset($data['user_id'])) {
            return response('Ongeldig verzoek', 403);
        }
        $w->instagramIngetrokken((string) $data['user_id'], false);

        return response('ok', 200);
    }

    /** Meta: verzoek om gegevens te verwijderen (Data deletion request). Meta verwacht een statusadres en een code. */
    public function instagramVerwijderen(Request $request, Webhooks $w): JsonResponse|Response
    {
        $data = $w->metaSignedRequest($request->input('signed_request'));
        if (! $data || ! isset($data['user_id'])) {
            return response('Ongeldig verzoek', 403);
        }
        $w->instagramIngetrokken((string) $data['user_id'], true);
        $code = Str::lower(Str::random(12));

        return response()->json(['url' => route('webhooks.instagram.verwijderd', $code), 'confirmation_code' => $code]);
    }

    public function verwijderd(string $code): Response
    {
        return response("Je verzoek {$code} is verwerkt: de koppeling is verbroken en de opgehaalde gegevens zijn verwijderd. Vragen? Mail naar MEBRANDING.", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /** Tijdelijke publieke link (ondertekend, 1 uur) naar een export, zodat Meta het bestand kan ophalen bij plaatsen. */
    public function bestand(StudioExport $export): Response
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($export->path), 404);

        return response()->file($disk->path($export->path), ['Content-Type' => $export->mime]);
    }
}
