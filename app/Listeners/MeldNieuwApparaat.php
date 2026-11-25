<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Melder;
use Illuminate\Auth\Events\Login;

/**
 * g-nieuw-apparaat: inloggen op een apparaat dat deze gebruiker nog niet eerder gebruikte.
 * Een apparaat is browser plus besturingssysteem uit de user agent (geen versies, geen IP-adres), bewaard in users.apparaten.
 * De eerste inlog ooit legt alleen vast; Inloggen als (Super Admin) telt niet mee.
 * {plaats} is niet betrouwbaar te bepalen zonder IP-locatie: daar staat "onbekende plaats".
 */
class MeldNieuwApparaat
{
    public function __construct(private Melder $melder) {}

    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User || ! app()->bound('request') || (app()->bound('session') && session()->has('inloggen_als_van'))) {
            return;
        }

        $apparaat = self::apparaat((string) request()->userAgent());
        $sleutel = hash('sha256', $apparaat);
        $bekend = $user->apparaten ?? [];
        $nieuw = $bekend !== [] && ! isset($bekend[$sleutel]);

        $bekend[$sleutel] = ['apparaat' => $apparaat, 'laatst' => now()->toIso8601String()];
        $user->forceFill(['apparaten' => $bekend])->saveQuietly();

        if ($nieuw) {
            $nu = now('Europe/Amsterdam');
            $this->melder->stuur('g-nieuw-apparaat', $user, [
                'apparaat' => $apparaat, 'plaats' => 'onbekende plaats', 'datum' => $nu->translatedFormat('l j F'), 'tijd' => $nu->format('H:i'),
            ], route('security.edit'));
        }
    }

    /** "Chrome op Windows", "Safari op iPhone"; onbekend wordt "onbekende browser op onbekend apparaat". */
    public static function apparaat(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'onbekende browser',
        };
        $systeem = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'Mac',
            str_contains($ua, 'CrOS') => 'Chromebook',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'onbekend apparaat',
        };

        return "{$browser} op {$systeem}";
    }
}
