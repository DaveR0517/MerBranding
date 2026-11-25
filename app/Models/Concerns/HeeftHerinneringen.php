<?php

namespace App\Models\Concerns;

/**
 * Een bericht maar één keer per ding: de kolom herinneringen (json) houdt per sleutel bij wanneer het ging.
 * Eerst vastleggen, dan versturen: liever één keer te weinig dan dubbel. Gebruikt door samenwerkingen, gebruikers, bedrijven,
 * creators, koppelingen, cursusdagen, inschrijvingen en betaalruns.
 */
trait HeeftHerinneringen
{
    public function initializeHeeftHerinneringen(): void
    {
        $this->mergeCasts(['herinneringen' => 'array']);
    }

    /** Legt vast dat het bericht met deze sleutel gaat. False als het al eerder ging. */
    public function markeerHerinnering(string $sleutel): bool
    {
        $gehad = $this->herinneringen ?? [];
        if (array_key_exists($sleutel, $gehad)) {
            return false;
        }
        $this->forceFill(['herinneringen' => $gehad + [$sleutel => now()->toIso8601String()]])->save();

        return true;
    }
}
