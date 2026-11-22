<?php

/*
| MeStudio: marketing-ontwerpen van MEBRANDING zelf (team). Waarden zijn instelbaar via .env.
*/

return [
    // Maximale grootte van een upload in de mediatheek, in MB (testadres: 95). Let op: ook PHP (upload_max_filesize, post_max_size) en nginx (client_max_body_size) moeten dit toelaten.
    'max_upload_mb' => (int) env('STUDIO_MAX_UPLOAD_MB', 95),

    // Maximale lengte van een videoverslag in seconden. VOORSTEL: lengte van de video, hooguit 90 seconden.
    'max_video_seconden' => (int) env('STUDIO_MAX_VIDEO_SECONDEN', 90),

    // Pad naar ffmpeg. Leeg of niet gevonden: de webm blijft bewaard en het scherm zegt dat eerlijk.
    'ffmpeg' => env('STUDIO_FFMPEG', 'ffmpeg'),

    // Vaste formaten (pixelmaten exact). Carrousel: meerdere pagina's van 1080x1350.
    'formaten' => [
        'instagram_post' => ['naam' => 'Instagram post 1:1', 'breedte' => 1080, 'hoogte' => 1080],
        'instagram_portret' => ['naam' => 'Instagram portret 4:5', 'breedte' => 1080, 'hoogte' => 1350],
        'story' => ['naam' => 'Story / Reel 9:16', 'breedte' => 1080, 'hoogte' => 1920],
        'carrousel' => ['naam' => 'Carrousel', 'breedte' => 1080, 'hoogte' => 1350],
        'facebook_post' => ['naam' => 'Facebook post', 'breedte' => 1200, 'hoogte' => 630],
        'linkedin_post' => ['naam' => 'LinkedIn post', 'breedte' => 1200, 'hoogte' => 1200],
    ],

    /*
    | Merkkit: de ENE bron voor de huisstijl in MeStudio. Super Admin past dit aan in het scherm Merkkit
    | (opgeslagen in studio_merkkit, elke wijziging in het logboek); App\Services\Studio\Merkkit voegt het samen.
    | Maten van tekst gelden voor 1080 breed; per formaat vermenigvuldigd met 'schaal'.
    */
    'merkkit' => [
        'palet' => [
            ['kleur' => '#3A3659', 'naam' => 'Aubergine'],
            ['kleur' => '#2E2B48', 'naam' => 'Aubergine donker'],
            ['kleur' => '#6D6794', 'naam' => 'Lila'],
            ['kleur' => '#614ED9', 'naam' => 'Violet'],
            ['kleur' => '#D9303E', 'naam' => 'Rood'],
            ['kleur' => '#FFFFFF', 'naam' => 'Wit'],
            ['kleur' => '#F6F5F9', 'naam' => 'Licht'],
            ['kleur' => '#2A2840', 'naam' => 'Inkt'],
        ],
        // Merkverloop: 135 graden van rood naar violet.
        'verloop' => ['van' => '#D9303E', 'naar' => '#614ED9', 'hoek' => 135],
        // Alleen deze twee lettertypes; niet bewerkbaar.
        'lettertypes' => ['Montserrat Variable', 'Noto Sans Variable'],
        'stijlen' => [
            'kop' => ['naam' => 'Kop', 'lettertype' => 'Montserrat Variable', 'grootte' => 80, 'gewicht' => 800, 'regelafstand' => 1.1, 'hoofdletters' => false],
            'subkop' => ['naam' => 'Subkop', 'lettertype' => 'Montserrat Variable', 'grootte' => 48, 'gewicht' => 700, 'regelafstand' => 1.2, 'hoofdletters' => false],
            'tekst' => ['naam' => 'Tekst', 'lettertype' => 'Noto Sans Variable', 'grootte' => 36, 'gewicht' => 400, 'regelafstand' => 1.35, 'hoofdletters' => false],
            'label' => ['naam' => 'Label', 'lettertype' => 'Montserrat Variable', 'grootte' => 30, 'gewicht' => 800, 'regelafstand' => 1.2, 'hoofdletters' => true],
            'cijfer' => ['naam' => 'Cijfer', 'lettertype' => 'Montserrat Variable', 'grootte' => 220, 'gewicht' => 900, 'regelafstand' => 1.0, 'hoofdletters' => false],
        ],
        // Logovarianten uit public/brand.
        'logos' => ['wit' => 'mebranding-logo-wit.svg', 'aubergine' => 'mebranding-logo-aubergine.svg', 'verloop' => 'mebranding-logo-verloop.svg'],
        // Per formaat: marge rondom, veilige zones (Story/Reel: knoppen van Instagram) en schaal van de tekststijlen.
        // Schaal is minstens breedte/1080, zodat de kleinste stijl ook op 1200 breed aan de minimale grootte voldoet.
        'formaten' => [
            'instagram_post' => ['marge' => 60, 'veilig_boven' => 0, 'veilig_onder' => 0, 'schaal' => 1],
            'instagram_portret' => ['marge' => 60, 'veilig_boven' => 0, 'veilig_onder' => 0, 'schaal' => 1],
            'story' => ['marge' => 60, 'veilig_boven' => 250, 'veilig_onder' => 340, 'schaal' => 1],
            'carrousel' => ['marge' => 60, 'veilig_boven' => 0, 'veilig_onder' => 0, 'schaal' => 1],
            'facebook_post' => ['marge' => 60, 'veilig_boven' => 0, 'veilig_onder' => 0, 'schaal' => 1.12],
            'linkedin_post' => ['marge' => 60, 'veilig_boven' => 0, 'veilig_onder' => 0, 'schaal' => 1.12],
        ],
        // Slepen klikt vast op dit raster (px).
        'raster' => 10,
        // Regels van de merkcontrole. Contrast volgens WCAG: kleine tekst 4,5:1, grote tekst 3:1;
        // "groot" is vanaf 24 px op het scherm van een telefoon (390 breed) waarop de post getoond wordt.
        'controle' => [
            'min_tekst' => 28,
            'contrast_klein' => 4.5,
            'contrast_groot' => 3.0,
            'groot_vanaf_scherm_px' => 24,
            'schermbreedte' => 390,
            'logo_min_breedte' => 200,
            'max_stijlen' => 3,
        ],
    ],

    'statussen' => ['concept' => 'Concept', 'klaar' => 'Klaar', 'ingepland' => 'Ingepland', 'geplaatst' => 'Geplaatst'],
];
