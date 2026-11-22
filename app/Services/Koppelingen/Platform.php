<?php

namespace App\Services\Koppelingen;

use App\Models\SocialAccount;

/**
 * Eén sociaal platform (fase 3). Alleen officiële API's, geen scraping (CLAUDE.md 8).
 * Zolang de sleutels ontbreken geeft elke stap Resultaat::nietIngesteld terug en draait alles handmatig.
 * Een fout van het platform wordt nooit een uitzondering: altijd Resultaat::fout met een leesbare melding.
 */
interface Platform
{
    /** Sleutel in de database: instagram of tiktok. */
    public function sleutel(): string;

    public function naam(): string;

    /** Zijn de sleutels van de ontwikkelaarsapp ingevuld (config/services.php)? */
    public function isIngesteld(): bool;

    /** Hoe lang een koppeling geldig is, in dagen (Instagram: token, TikTok: vernieuwtoken). */
    public function tokenDagen(): int;

    /**
     * Adres waar de gebruiker inlogt bij het platform en toestemming geeft (data.url).
     *
     * @param  list<string>|null  $scopes  null = de rechten voor creators en ondernemers (alleen cijfers)
     */
    public function autorisatieUrl(string $terugUrl, string $state, ?array $scopes = null): Resultaat;

    /** Code van het platform inwisselen voor tokens (data: access_token, refresh_token, expires_at, token_expires_at, handle, external_id, scopes). */
    public function wisselCodeIn(string $code, string $terugUrl): Resultaat;

    /** Token verlengen voordat het verloopt (zelfde data als wisselCodeIn). */
    public function vernieuw(SocialAccount $account): Resultaat;

    /** Profiel (data: handle, naam, foto, volgers, volgend, media, likes_totaal). */
    public function profiel(SocialAccount $account): Resultaat;

    /** Recente posts of video's, nieuwste eerst (data.items, zie BasisPlatform::item; data.cijfers_mee: zitten de cijfers er al in). */
    public function media(SocialAccount $account, int $paginas = 1): Resultaat;

    /** Cijfers van één post ophalen (data: metriek => waarde). */
    public function haalCijfersOp(SocialAccount $account, string $postUrl): Resultaat;
}
