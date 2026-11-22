<?php

namespace App\Services\Koppelingen;

use App\Models\SocialAccount;

/** Gedeeld gedrag: zonder sleutels "niet ingesteld". De echte API-aanroepen volgen zodra de sleutels er zijn. */
abstract class BasisPlatform implements Platform
{
    public function isIngesteld(): bool
    {
        return filled(config("services.{$this->sleutel()}.client_id")) && filled(config("services.{$this->sleutel()}.client_secret"));
    }

    public function autorisatieUrl(string $terugUrl, string $state): Resultaat
    {
        if (! $this->isIngesteld()) {
            return Resultaat::nietIngesteld($this->naam());
        }

        return Resultaat::gelukt(['url' => $this->inlogAdres().'?'.http_build_query($this->inlogParameters($terugUrl, $state))]);
    }

    public function wisselCodeIn(string $code, string $terugUrl): Resultaat
    {
        if (! $this->isIngesteld()) {
            return Resultaat::nietIngesteld($this->naam());
        }

        // Nog niet gebouwd: het inwisselen doen we pas met echte sleutels en een goedgekeurde app (fase 3).
        return Resultaat::fout("Koppelen met {$this->naam()} is nog niet klaar. Vul je account voorlopig handmatig in.");
    }

    public function haalCijfersOp(SocialAccount $account, string $postUrl): Resultaat
    {
        if (! $this->isIngesteld()) {
            return Resultaat::nietIngesteld($this->naam());
        }

        return Resultaat::fout('Cijfers ophalen is nog niet gebouwd. Lever de cijfers handmatig aan met een screenshot.');
    }

    abstract protected function inlogAdres(): string;

    /** @return array<string, string> */
    abstract protected function inlogParameters(string $terugUrl, string $state): array;
}
