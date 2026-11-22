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
    | Pad van het portaal. Leeg op app.mebranding.nl. Alleen zolang website en portaal
    | hetzelfde testadres delen (Forge-testdomein, voor de DNS-overstap): APP_PREFIX=app.
    */
    'app_prefix' => env('APP_PREFIX', ''),

    /*
    | Wie de meldingen van nieuwe aanmeldingen van de website krijgt.
    */
    'team_email' => env('MEBRANDING_TEAM_EMAIL', 'info@mebranding.nl'),

    /*
    | Spamwering voor de formulieren: maximaal aantal inzendingen per IP per uur.
    | VOORSTEL: 5 per uur.
    */
    'lead_rate_limit_per_hour' => (int) env('MEBRANDING_LEAD_RATE_LIMIT', 5),

    /*
    | Creatorniveaus: punten per samenwerking en vergoeding (CLAUDE.md 7.1 en 7.2).
    | Alleen Platinum en Elite krijgen geld (besloten 18-11-2026). Punten en bedragen: VOORSTEL.
    */
    'levels' => [
        'bronze' => ['name' => 'Bronze', 'points' => 3, 'fee_cents' => 0],
        'silver' => ['name' => 'Silver', 'points' => 4, 'fee_cents' => 0],
        'gold' => ['name' => 'Gold', 'points' => 5, 'fee_cents' => 0],
        'platinum' => ['name' => 'Platinum', 'points' => 6, 'fee_cents' => 12500],
        'elite' => ['name' => 'Elite', 'points' => 8, 'fee_cents' => 16000],
    ],

    /*
    | Timers (CLAUDE.md 7.4). Besloten: meetperiode 14 dagen, zelf bevestigen 24 uur.
    | VOORSTEL: reactietermijn uitnodiging, deadline content, grens contentcontrole.
    */
    'timers' => [
        'invitation_hours' => 48, // VOORSTEL
        'self_confirm_hours' => 24,
        'content_deadline_days' => 5, // VOORSTEL: dagen na het bezoek
        'review_cutoff' => '16:00', // VOORSTEL: later binnen = volgende werkdag
        'measure_days' => 14,
        'late_cancel_hours' => 48, // VOORSTEL: annuleren ondernemer korter dan dit voor het bezoek = verbruikt
    ],

    /*
    | Video's. Tot Cloudflare R2 en Bunny Stream er zijn: privé opslag op de server.
    */
    'video_disk' => env('MEBRANDING_VIDEO_DISK', 'local'),
    'video_max_mb' => (int) env('MEBRANDING_VIDEO_MAX_MB', 500),

    /*
    | Versie van het contract per klus dat de creator tekent. Placeholder tot de juridische teksten er zijn.
    */
    'contract_version' => '0.1-concept',

];
