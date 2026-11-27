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
    | Let op: APP_URL dan zonder pad (https://host, niet https://host/app), anders staat /app dubbel in de links.
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

    // Kaart: adressen opzoeken bij PDOK (App\Services\Geocoder). Uit in de tests, die PDOK nabootsen.
    'geocoderen' => (bool) env('MEBRANDING_GEOCODEREN', true),

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
        // Herinneringen (App\Services\Herinneringen). De reactieherinnering gaat halverwege invitation_hours.
        'deadline_reminder_hours' => 48, // c-deadline; de tekst van het sjabloon noemt 48 uur
        'post_reminder_days' => 2, // c-herinnering-post: dagen na de publicatiedatum zonder link
        'zoeken_lang_workdays' => 3, // VOORSTEL: t-zoeken-lang, werkdagen zonder creator na de aanvraag
        'controle_lang_workdays' => 1, // VOORSTEL: t-controle-lang, werkdagen dat een versie ter controle staat
    ],

    /*
    | Drempels voor teamsignalen. VOORSTEL.
    */
    'drempels' => [
        'oordeel_laag' => 3, // VOORSTEL: t-oordeel-laag bij een beoordeling van minder dan dit aantal sterren (1 tot 5)
    ],

    /*
    | Tweestapsverificatie verplicht voor het team (CLAUDE.md 5). Standaard aan.
    | Uit te zetten met MEBRANDING_TEAM_2FA=false (tests, eerste inrichting) of door een Super Admin in Instellingen.
    */
    'team_2fa_verplicht' => (bool) env('MEBRANDING_TEAM_2FA', true),

    /*
    | Video's. Tot Cloudflare R2 en Bunny Stream er zijn: privé opslag op de server.
    */
    'video_disk' => env('MEBRANDING_VIDEO_DISK', 'local'),
    'video_max_mb' => (int) env('MEBRANDING_VIDEO_MAX_MB', 500),

    /*
    | Versies van wat de creator tekent: de raamovereenkomst (eenmalig) en het kluscontract (per klus).
    | Basisafspraken; een nieuwe versie vraagt een nieuwe handtekening.
    */
    'framework_version' => '1.0',
    'contract_version' => '1.0',

    /*
    | Vaste accounts voor de eigenaren van MEBRANDING, uit de .env op de server (nooit in Git):
    | DAIVE_ACCOUNT_{n}_EMAIL, _NAAM, _ROL, _WACHTWOORD. Rol "eigenaar" wordt Super Admin.
    | Aangemaakt door php artisan mebranding:accounts (draait bij elke deploy).
    */
    'accounts' => collect(range(1, 10))
        ->map(fn ($n) => [
            'email' => env("DAIVE_ACCOUNT_{$n}_EMAIL"),
            'naam' => env("DAIVE_ACCOUNT_{$n}_NAAM"),
            'rol' => env("DAIVE_ACCOUNT_{$n}_ROL", 'eigenaar'),
            'wachtwoord' => env("DAIVE_ACCOUNT_{$n}_WACHTWOORD"),
        ])
        ->filter(fn ($a) => filled($a['email']) && filled($a['wachtwoord']))
        ->values()
        ->all(),

];
