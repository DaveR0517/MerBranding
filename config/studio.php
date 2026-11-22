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

    'statussen' => ['concept' => 'Concept', 'klaar' => 'Klaar', 'ingepland' => 'Ingepland', 'geplaatst' => 'Geplaatst'],
];
