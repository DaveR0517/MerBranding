<?php

namespace App\Services\Koppelingen;

use App\Models\SocialAccount;

/**
 * Eén sociaal platform (fase 3). Alleen officiële API's, geen scraping (CLAUDE.md 8).
 * Zolang de sleutels ontbreken geeft elke stap Resultaat::nietIngesteld terug en draait alles handmatig.
 */
interface Platform
{
    /** Sleutel in de database: instagram of tiktok. */
    public function sleutel(): string;

    public function naam(): string;

    /** Zijn de sleutels van de ontwikkelaarsapp ingevuld (config/services.php)? */
    public function isIngesteld(): bool;

    /** Hoe lang een token geldig is, in dagen. */
    public function tokenDagen(): int;

    /** Adres waar de gebruiker inlogt bij het platform en toestemming geeft (data.url). */
    public function autorisatieUrl(string $terugUrl, string $state): Resultaat;

    /** Code van het platform inwisselen voor tokens (data.access_token, data.refresh_token, data.expires_at, data.handle). */
    public function wisselCodeIn(string $code, string $terugUrl): Resultaat;

    /** Cijfers van één post ophalen (data: metriek => waarde). */
    public function haalCijfersOp(SocialAccount $account, string $postUrl): Resultaat;
}
