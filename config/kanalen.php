<?php

/*
| Module eigen kanalen: het Instagram- en TikTok-account van MEBRANDING zelf.
| Sleutels staan in config/services.php (INSTAGRAM_*, TIKTOK_*). Waarden hieronder zijn VOORSTEL.
*/

return [

    // Rechten die we vragen bij het koppelen van het eigen account.
    'scopes' => [
        'instagram' => [
            'instagram_business_basic',
            'instagram_business_manage_messages',
            'instagram_business_manage_comments',
            'instagram_business_manage_insights',
            'instagram_business_content_publish',
        ],
        'tiktok' => ['user.info.basic', 'user.info.profile', 'user.info.stats', 'video.list'],
        // Klaar voor later: met de Content Posting API (na review door TikTok) erbij zetten.
        'tiktok_later' => ['video.publish'],
    ],

    // Tokens verlengen. Instagram: token geldt 60 dagen, verlengen vanaf dag 50 (dus 10 dagen voor het einde).
    'instagram_verleng_dagen_voor_einde' => 10, // VOORSTEL
    // TikTok: toegangstoken geldt 24 uur; verlengen zodra het binnen deze uren verloopt (het uurlijkse ophalen doet dat).
    'tiktok_verleng_uren_voor_einde' => 3, // VOORSTEL

    // Ophalen (elk uur). Hoeveel pagina's media en voor hoeveel recente posts de cijfers ververst worden.
    'media_paginas' => 4, // VOORSTEL: 4 x 25 posts
    'inzichten_dagen' => 60, // VOORSTEL: cijfers verversen van posts tot 60 dagen oud
    'inzichten_max' => 40, // VOORSTEL: hooguit zoveel posts per uur (API-limiet van Meta)
    'gesprekken_max' => 20, // VOORSTEL

    // Berichten: Meta staat een antwoord alleen toe binnen 24 uur na het laatste bericht van de ander.
    'berichtvenster_uren' => 24,

    // Plaatsen vanuit MeStudio: hoe vaak en hoe lang wachten tot Instagram de video verwerkt heeft.
    'plaatsen_pogingen' => 30, // VOORSTEL
    'plaatsen_wacht_seconden' => 10, // VOORSTEL
    'plaatsen_url_minuten' => 60, // tijdelijke publieke link naar het exportbestand

];
