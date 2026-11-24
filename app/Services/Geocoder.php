<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adres naar plek op de kaart via de PDOK Locatieserver (Kadaster, gratis, geen sleutel, alleen Nederland).
 * Met een adres op de deur nauwkeurig; met alleen een plaats het midden van die woonplaats.
 */
class Geocoder
{
    private const URL = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free';

    /** @return array{lat: float, lng: float, precisie: string}|null */
    public function zoek(?string $adres, ?string $plaats): ?array
    {
        $adres = trim((string) $adres);
        $plaats = trim((string) $plaats);
        if (! config('mebranding.geocoderen') || ($adres === '' && $plaats === '')) {
            return null;
        }

        $metAdres = $adres !== '';
        try {
            $antwoord = Http::timeout(8)->retry(2, 300, throw: false)->get(self::URL, [
                'q' => trim("{$adres} {$plaats}"),
                'fq' => $metAdres ? 'type:(adres OR weg OR postcode)' : 'type:woonplaats',
                'rows' => 1,
                'fl' => 'centroide_ll,type',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Geocoderen mislukt', ['fout' => $e->getMessage()]);

            return null;
        }

        $doc = $antwoord->successful() ? ($antwoord->json('response.docs.0') ?? null) : null;
        if (! $doc || ! preg_match('/POINT\(([-\d.]+) ([-\d.]+)\)/', $doc['centroide_ll'] ?? '', $m)) {
            // Adres niet gevonden: dan in elk geval de plaats.
            return $metAdres && $plaats !== '' ? $this->zoek(null, $plaats) : null;
        }

        return ['lat' => (float) $m[2], 'lng' => (float) $m[1], 'precisie' => $doc['type'] === 'woonplaats' ? 'plaats' : 'adres'];
    }

    /** Zet de plek op een vestiging of aanmelding. Geeft true als er een plek gevonden is. */
    public function plaats(Model $model): bool
    {
        [$adres, $plaats] = match (true) {
            $model instanceof Location => [$model->address, $model->city],
            $model instanceof Lead => [$model->data['adres'] ?? null, $model->city],
            default => [null, null],
        };

        $plek = $this->zoek($adres, $plaats);
        $model->forceFill([
            'lat' => $plek['lat'] ?? null, 'lng' => $plek['lng'] ?? null,
            'geo_precisie' => $plek['precisie'] ?? null, 'geocoded_at' => now(),
        ])->saveQuietly();

        return $plek !== null;
    }
}
