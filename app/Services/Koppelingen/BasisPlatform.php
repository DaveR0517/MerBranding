<?php

namespace App\Services\Koppelingen;

use App\Models\SocialAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gedeeld gedrag van de platforms: zonder sleutels "niet ingesteld", HTTP-aanroepen met een vaste time-out
 * en foutafhandeling die altijd een Resultaat geeft (nooit een uitzondering naar het scherm).
 */
abstract class BasisPlatform implements Platform
{
    public function isIngesteld(): bool
    {
        return filled(config("services.{$this->sleutel()}.client_id")) && filled(config("services.{$this->sleutel()}.client_secret"));
    }

    public function autorisatieUrl(string $terugUrl, string $state, ?array $scopes = null): Resultaat
    {
        if (! $this->isIngesteld()) {
            return Resultaat::nietIngesteld($this->naam());
        }

        return Resultaat::gelukt(['url' => $this->inlogAdres().'?'.http_build_query($this->inlogParameters($terugUrl, $state, $scopes ?? $this->standaardScopes()))]);
    }

    public function haalCijfersOp(SocialAccount $account, string $postUrl): Resultaat
    {
        if (! $this->isIngesteld()) {
            return Resultaat::nietIngesteld($this->naam());
        }

        $media = $this->media($account, 4);
        if (! $media->isGelukt()) {
            return $media;
        }

        $id = $this->postId($postUrl);
        $item = collect($media->data['items'])->first(fn (array $i) => $i['permalink'] && ($id ? str_contains($i['permalink'], $id) || $i['extern_id'] === $id : rtrim($i['permalink'], '/') === rtrim($postUrl, '/')));
        if (! $item) {
            return Resultaat::fout('Deze post staat niet tussen de recente posts van het gekoppelde account.');
        }

        return $media->data['cijfers_mee'] ? Resultaat::gelukt($item['cijfers']) : $this->cijfersVan($account, $item);
    }

    /** Herkenbaar deel van een postadres (shortcode of video-id), of null als het adres het niet heeft. */
    protected function postId(string $postUrl): ?string
    {
        return preg_match('~/(?:p|reel|tv|video)/([A-Za-z0-9_-]+)~', $postUrl, $m) ? $m[1] : null;
    }

    /**
     * Cijfers voor één item als de medialijst ze niet meelevert.
     *
     * @param  array<string, mixed>  $item
     */
    protected function cijfersVan(SocialAccount $account, array $item): Resultaat
    {
        return Resultaat::gelukt($item['cijfers']);
    }

    /**
     * Eén media-item in een vaste vorm, gelijk voor beide platforms.
     *
     * @param  array<string, int|null>  $cijfers  views, reach, likes, comments, shares, saved, total_interactions
     * @return array<string, mixed>
     */
    protected function item(string $id, ?string $soort, ?string $product, ?string $bijschrift, ?string $mediaUrl, ?string $miniatuur, ?string $permalink, ?int $duur, ?string $geplaatst, array $cijfers): array
    {
        return [
            'extern_id' => $id, 'soort' => $soort, 'product' => $product, 'bijschrift' => $bijschrift, 'media_url' => $mediaUrl,
            'miniatuur_url' => $miniatuur, 'permalink' => $permalink, 'duur' => $duur, 'geplaatst_op' => $geplaatst,
            'cijfers' => array_filter($cijfers, fn ($w) => $w !== null),
        ];
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(20)->connectTimeout(10)->acceptJson();
    }

    /**
     * Voer een aanroep uit en maak er een Resultaat van. $lees haalt de data uit een geslaagd antwoord.
     *
     * @param  callable(): Response  $aanroep
     * @param  callable(array<string, mixed>): array<string, mixed>  $lees
     */
    protected function roep(string $wat, callable $aanroep, callable $lees): Resultaat
    {
        if (! $this->isIngesteld()) {
            return Resultaat::nietIngesteld($this->naam());
        }

        try {
            $antwoord = $aanroep();
        } catch (ConnectionException $e) {
            Log::warning("{$this->naam()}: {$wat} niet bereikbaar", ['fout' => $e->getMessage()]);

            return Resultaat::fout("{$this->naam()} is nu niet bereikbaar. Het wordt later opnieuw geprobeerd.");
        }

        $json = (array) $antwoord->json();
        $fout = $this->foutUit($antwoord, $json);
        if ($fout !== null) {
            Log::warning("{$this->naam()}: {$wat} mislukt", ['status' => $antwoord->status(), 'fout' => $fout]);

            return Resultaat::fout("{$this->naam()} gaf een fout bij {$wat}: {$fout}", ['http_status' => $antwoord->status(), 'token_ongeldig' => $this->tokenOngeldig($antwoord, $json)]);
        }

        return Resultaat::gelukt($lees($json));
    }

    /**
     * Foutmelding uit een antwoord, of null als het goed ging.
     *
     * @param  array<string, mixed>  $json
     */
    abstract protected function foutUit(Response $antwoord, array $json): ?string;

    /**
     * Is het token ongeldig of ingetrokken (dan moet het team opnieuw koppelen)?
     *
     * @param  array<string, mixed>  $json
     */
    abstract protected function tokenOngeldig(Response $antwoord, array $json): bool;

    /** @return list<string> */
    abstract protected function standaardScopes(): array;

    abstract protected function inlogAdres(): string;

    /**
     * @param  list<string>  $scopes
     * @return array<string, string>
     */
    abstract protected function inlogParameters(string $terugUrl, string $state, array $scopes): array;
}
