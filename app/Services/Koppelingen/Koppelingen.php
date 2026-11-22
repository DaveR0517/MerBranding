<?php

namespace App\Services\Koppelingen;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Creator;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Koppelingen van creators en bedrijven met Instagram en TikTok (fase 3, CLAUDE.md 8).
 * Toestemming komt altijd eerst en wordt gelogd met datum en versie. Zonder sleutels: handmatige modus.
 */
class Koppelingen
{
    public function platform(string $sleutel): Platform
    {
        $klasse = config("content.koppelingen.platforms.{$sleutel}");
        abort_unless($klasse, 404);

        return app($klasse);
    }

    /** @return list<string> */
    public function sleutels(): array
    {
        return array_keys(config('content.koppelingen.platforms'));
    }

    public function account(Creator|Company $eigenaar, string $platform): SocialAccount
    {
        $this->platform($platform);

        return SocialAccount::firstOrNew(['owner_type' => $eigenaar->getMorphClass(), 'owner_id' => $eigenaar->getKey(), 'platform' => $platform]);
    }

    /** Toestemming geven (AVG): datum en versie op de koppeling en een regel in het logboek. */
    public function toestemming(Creator|Company $eigenaar, string $platform, User $door, ?string $ip): SocialAccount
    {
        $account = $this->account($eigenaar, $platform);
        $account->fill(['consent_version' => config('content.consent_version'), 'consent_at' => now()]);
        $account->status ??= 'niet_gekoppeld';
        $account->save();

        $this->log($account, $door, 'toestemming_gegeven', null, ['versie' => $account->consent_version, 'platform' => $platform, 'ip' => $ip]);

        return $account;
    }

    /** Handmatige modus: gebruikersnaam en bevestiging van een professioneel account, zonder tokens. */
    public function handmatig(Creator|Company $eigenaar, string $platform, string $handle, User $door): SocialAccount
    {
        $account = $this->account($eigenaar, $platform);
        if (! $account->exists || ! $account->hasCurrentConsent()) {
            throw ValidationException::withMessages(['akkoord' => 'Geef eerst toestemming voordat je je account koppelt.']);
        }

        $van = $account->status;
        $account->fill([
            'handle' => '@'.ltrim(trim($handle), '@'), 'professional_confirmed' => true, 'status' => 'handmatig',
            'access_token' => null, 'refresh_token' => null, 'expires_at' => null, 'last_error' => null,
        ])->save();

        $this->log($account, $door, 'koppeling_handmatig', $van, ['handle' => $account->handle]);

        return $account;
    }

    /** Verbreken: tokens weg, toestemming ingetrokken. De gebruikersnaam blijft staan als hulp bij opnieuw koppelen. */
    public function verbreken(SocialAccount $account, User $door): void
    {
        $van = $account->status;
        $account->fill([
            'status' => 'niet_gekoppeld', 'access_token' => null, 'refresh_token' => null, 'expires_at' => null, 'token_expires_at' => null, 'scopes' => null,
            'consent_version' => null, 'consent_at' => null, 'professional_confirmed' => false,
        ])->save();

        $this->log($account, $door, 'toestemming_ingetrokken', $van);
    }

    /** Tokens opslaan na een geslaagde terugkoppeling van het platform (pas bruikbaar met echte sleutels). */
    public function tokensOpslaan(SocialAccount $account, Resultaat $resultaat, User $door): void
    {
        $van = $account->status;
        $account->fill([
            'status' => 'gekoppeld', 'professional_confirmed' => true,
            'handle' => $resultaat->data['handle'] ?? $account->handle,
            'external_id' => $resultaat->data['external_id'] ?? $account->external_id,
            'scopes' => $resultaat->data['scopes'] ?? $account->scopes,
            'access_token' => $resultaat->data['access_token'] ?? null,
            'refresh_token' => $resultaat->data['refresh_token'] ?? null,
            'expires_at' => $resultaat->data['expires_at'] ?? now()->addDays($this->platform($account->platform)->tokenDagen()),
            'token_expires_at' => $resultaat->data['token_expires_at'] ?? null,
            'last_error' => null,
        ])->save();

        $this->log($account, $door, 'koppeling_gekoppeld', $van);
    }

    /**
     * Alles wat een koppelingenscherm nodig heeft, zonder tokens.
     *
     * @return list<array<string, mixed>>
     */
    public function overzicht(Creator|Company $eigenaar): array
    {
        return array_map(function (string $sleutel) use ($eigenaar) {
            $platform = $this->platform($sleutel);
            $account = $this->account($eigenaar, $sleutel);

            return [
                'platform' => $sleutel,
                'naam' => $platform->naam(),
                'ingesteld' => $platform->isIngesteld(),
                'status' => $account->status ?? 'niet_gekoppeld',
                'handle' => $account->handle,
                'professioneel' => (bool) $account->professional_confirmed,
                'toestemming' => $account->exists && $account->hasCurrentConsent(),
                'toestemming_op' => $account->consent_at?->toIso8601String(),
                'toestemming_versie' => $account->consent_version,
                'verloopt_op' => $account->expires_at?->toIso8601String(),
                'laatste_sync' => $account->last_synced_at?->toIso8601String(),
                'waarschuwing' => $account->exists ? $account->warning() : null,
            ];
        }, $this->sleutels());
    }

    /**
     * De toestemmingstekst (AVG: wat, met wie, hoe lang). Placeholder met versienummer tot de juridische teksten er zijn.
     *
     * @return array<string, mixed>
     */
    public function toestemmingstekst(string $voor): array
    {
        $creator = $voor === 'creator';

        return [
            'versie' => config('content.consent_version'),
            'wat' => $creator
                ? ['Je gebruikersnaam en accounttype', 'Cijfers van je posts voor MEBRANDING-samenwerkingen: weergaven, bereik, likes, reacties, shares en opslagen', 'Cijfers over je publiek per regio, alleen als totaal']
                : ['Je gebruikersnaam en accounttype', 'Profielbezoeken en nieuwe volgers per dag', 'Cijfers van posts op je eigen account, om ze naast de video\'s van creators te zetten'],
            'niet' => ['We plaatsen, wijzigen of verwijderen nooit iets op je account', 'We zien je wachtwoord niet: je logt in bij het platform zelf', 'We lezen of sturen geen berichten'],
            'met_wie' => $creator
                ? 'Het team van MEBRANDING. De ondernemer ziet alleen de cijfers van de post voor zijn eigen samenwerking, nooit je hele account.'
                : 'Alleen het team van MEBRANDING en de gebruikers van je eigen bedrijf.',
            'hoe_lang' => 'Zolang de koppeling actief is. Na verbreken halen we niets meer op; eerder opgehaalde cijfers bij een samenwerking bewaren we volgens de bewaartermijn (VOORSTEL: 2 jaar na afronden).',
        ];
    }

    public function log(SocialAccount $account, ?User $door, string $event, ?string $van, ?array $data = null): void
    {
        ActivityLog::create([
            'user_id' => $door?->id, 'subject_type' => $account->getMorphClass(), 'subject_id' => $account->id, 'event' => $event,
            'from_status' => $van, 'to_status' => $account->status, 'data' => $data,
        ]);
    }

    /** Naam van de eigenaar voor de monitor. */
    public static function eigenaarNaam(?Model $eigenaar): string
    {
        return match (true) {
            $eigenaar instanceof Creator => $eigenaar->user->name,
            $eigenaar instanceof Company => $eigenaar->name,
            default => 'Onbekend',
        };
    }
}
