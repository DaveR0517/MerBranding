<?php

namespace App\Models\Concerns;

/**
 * Een herinnering of signaal gaat maar één keer per ding: de sleutel komt met het moment in de json-kolom `herinneringen`.
 * Gebruikt door samenwerkingen (keten) en gebruikers (account).
 */
trait HeeftHerinneringen
{
    /** Legt vast dat de herinnering gaat. False als hij al eerder ging. Eerst vastleggen, dan versturen: liever één keer te weinig dan dubbel. */
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
