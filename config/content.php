<?php

use App\Services\Koppelingen\InstagramPlatform;
use App\Services\Koppelingen\TikTokPlatform;

/*
| Module content en koppelingen (CLAUDE.md 8 en 9, fase 3 en 7).
| Waarden zijn VOORSTEL tenzij anders vermeld. Lees altijd via config(), dan werkt beheer in de database vanzelf.
*/

return [

    /*
    | Versie van de toestemmingstekst bij koppelen (AVG: wat, met wie, hoe lang).
    | Placeholder tot de juridische teksten er zijn (open punt 11). Nieuwe versie = opnieuw toestemming vragen.
    */
    'consent_version' => '0.1-concept',

    /*
    | Koppelingen. Instagram-tokens gelden 60 dagen (Instagram Login), waarschuwen 7 dagen vooraf.
    */
    'koppelingen' => [
        'warn_days' => 7,
        'platforms' => [
            'instagram' => InstagramPlatform::class,
            'tiktok' => TikTokPlatform::class,
        ],
    ],

    /*
    | Gebruiksrechten op video's (open punt 7: per pakket nog niet besloten).
    | VOORSTEL uit het ontwerp: organisch en website 12 maanden, advertenties 3 maanden, start bij Live.
    | Meldingen aan de eigenaar 30 en 7 dagen voor de einddatum (sjabloon o-rechten).
    */
    'usage_rights' => [
        'organisch_months' => 12, // VOORSTEL
        'advertentie_months' => 3, // VOORSTEL
        'notify_days' => [30, 7],
    ],

];
