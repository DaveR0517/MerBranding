<?php

namespace App\Services;

use App\Models\Creator;
use Carbon\CarbonImmutable;

/**
 * "Ik ben beschikbaar" (docs/plan-kaart-en-snelle-klussen.md, fase 2). Geen spoor: alleen de laatst gedeelde plek,
 * afgerond op drie decimalen (ongeveer 100 meter), en na afloop gewist. Zonder plek telt de woonplaats.
 */
class Beschikbaarheid
{
    /** Keuzes in de pop-up: aantal uren, of de rest van de dag (tot 22:00). */
    public const DUUR = ['2' => 2, '4' => 4, 'dag' => null];

    public function aan(Creator $creator, string $duur, ?float $lat, ?float $lng): CarbonImmutable
    {
        $nu = CarbonImmutable::now('Europe/Amsterdam');
        $tot = self::DUUR[$duur] ?? null ? $nu->addHours(self::DUUR[$duur]) : $nu->setTime(22, 0);
        if ($tot->lessThanOrEqualTo($nu)) {
            $tot = $nu->addHours(2); // na 22:00 gekozen voor de rest van de dag: toch twee uur
        }

        $creator->forceFill([
            'beschikbaar_tot' => $tot,
            'beschikbaar_lat' => $lat !== null ? round($lat, 3) : null,
            'beschikbaar_lng' => $lng !== null ? round($lng, 3) : null,
        ])->save();

        return $tot;
    }

    public function uit(Creator $creator): void
    {
        $creator->forceFill(['beschikbaar_tot' => null, 'beschikbaar_lat' => null, 'beschikbaar_lng' => null])->save();
    }

    /** Verlopen beschikbaarheid wissen, plek inbegrepen (planner, elk kwartier). */
    public function opruimen(): int
    {
        return Creator::whereNotNull('beschikbaar_tot')->where('beschikbaar_tot', '<=', now())
            ->update(['beschikbaar_tot' => null, 'beschikbaar_lat' => null, 'beschikbaar_lng' => null]);
    }
}
