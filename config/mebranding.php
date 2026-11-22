<?php

return [

    /*
    | Domeinen. Eén Laravel-applicatie bedient de website en de portalen.
    | De demo (demo.mebranding.nl) is een aparte statische site uit de map /demo.
    */
    'domains' => [
        'site' => env('SITE_DOMAIN', 'mebranding.nl'),
        'app' => env('APP_DOMAIN', 'app.mebranding.nl'),
    ],

    /*
    | Wie de meldingen van nieuwe aanmeldingen van de website krijgt.
    */
    'team_email' => env('MEBRANDING_TEAM_EMAIL', 'info@mebranding.nl'),

    /*
    | Spamwering voor de formulieren: maximaal aantal inzendingen per IP per uur.
    | VOORSTEL: 5 per uur.
    */
    'lead_rate_limit_per_hour' => (int) env('MEBRANDING_LEAD_RATE_LIMIT', 5),

];
