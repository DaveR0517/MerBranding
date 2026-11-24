<?php

namespace App\Services;

use App\Models\PushAbonnement;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush as Verzender;

/**
 * Pushmeldingen naar de webapp (Web Push met VAPID, geen Apple- of Google-account nodig).
 * De sleutels maakt de server zelf aan en bewaart ze versleuteld (APP_KEY) in de gedeelde opslag,
 * zodat ze nooit in Git of in een gesprek staan en een deploy ze niet weggooit.
 */
class Webpush
{
    private const SLEUTELBESTAND = 'webpush/vapid.json';

    /** @return array{publicKey: string, privateKey: string} */
    public function sleutels(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::SLEUTELBESTAND)) {
            $disk->put(self::SLEUTELBESTAND, Crypt::encryptString(json_encode(VAPID::createVapidKeys())));
        }

        return json_decode(Crypt::decryptString($disk->get(self::SLEUTELBESTAND)), true);
    }

    public function publiekeSleutel(): string
    {
        return $this->sleutels()['publicKey'];
    }

    /** Stuurt naar alle apparaten van deze gebruiker; apparaten die niet meer bestaan, vallen af. Geeft het aantal geslaagde terug. */
    public function stuur(User $user, string $titel, string $tekst, string $url): int
    {
        $abonnementen = PushAbonnement::where('user_id', $user->id)->get();
        if ($abonnementen->isEmpty()) {
            return 0;
        }

        $push = new Verzender(['VAPID' => ['subject' => 'https://'.config('mebranding.domains.site')] + $this->sleutels()], ['TTL' => 86400, 'urgency' => 'normal']);
        $bericht = json_encode(['title' => $titel, 'body' => $tekst, 'url' => $url, 'tag' => md5($titel.$url)]);
        foreach ($abonnementen as $a) {
            $push->queueNotification(Subscription::create([
                'endpoint' => $a->endpoint, 'publicKey' => $a->public_key, 'authToken' => $a->auth_token, 'contentEncoding' => $a->content_encoding,
            ]), $bericht);
        }

        $gelukt = 0;
        foreach ($push->flush() as $rapport) {
            $endpoint = $rapport->getRequest()->getUri()->__toString();
            if ($rapport->isSuccess()) {
                $gelukt++;
                PushAbonnement::where('endpoint', $endpoint)->update(['laatst_gebruikt_op' => now()]);
            } elseif ($rapport->isSubscriptionExpired()) {
                PushAbonnement::where('endpoint', $endpoint)->delete();
            } else {
                Log::warning('Webpush mislukt', ['reden' => $rapport->getReason()]);
            }
        }

        return $gelukt;
    }
}
