<?php

/*
| MEBRANDING Academy (CLAUDE.md fase 5, docs/02 flow 3). Open punten staan hier als instelbare VOORSTEL-waarden.
*/

return [
    // VOORSTEL: prijs per cursusdag in centen, incl. btw (open punt 9). Per cursusdag aan te passen.
    'price_cents' => (int) env('ACADEMY_PRICE_CENTS', 29500),

    // OPEN: is het praktijkexamen verplicht voor het certificaat? Standaard voor een nieuwe cursusdag; per dag instelbaar.
    'exam_required' => (bool) env('ACADEMY_EXAM_REQUIRED', true),

    // VOORSTEL: standaard maximaal aantal deelnemers per cursusdag.
    'max_participants' => 16,

    // VOORSTEL: wie van de wachtlijst een plek krijgt, heeft zoveel uur om te betalen.
    'offer_hours' => 24,

    // VOORSTEL: praktijkexamen inleveren binnen zoveel dagen na de cursusdag; herkansing binnen zoveel dagen.
    'exam_days' => 7,
    'retake_days' => 14,

    // VOORSTEL: minimumleeftijd bij inschrijven (open punt, v1 nam 18).
    'min_age' => 18,

    // Screening na Word creator: termijn in werkdagen (aanname v1, docs/02 flow 2).
    'screening_workdays' => 5,

    // Praktijkexamen: beoordelingslijst (docs/02 flow 3 stap 7). Voldoende bij gemiddeld 3 of hoger en geen enkele 1.
    'exam_videos' => 3,
    'exam_criteria' => [
        ['label' => 'Hook', 'hint' => 'Eerste 2 seconden pakken'],
        ['label' => 'Licht', 'hint' => 'Daglicht, geen harde schaduw'],
        ['label' => 'Geluid', 'hint' => 'Verstaanbaar, weinig ruis'],
        ['label' => 'Beeld', 'hint' => 'Stabiel, 9:16, goed kader'],
        ['label' => 'Boodschap', 'hint' => 'Duidelijk wat de zaak biedt'],
        ['label' => 'CTA', 'hint' => 'Oproep aan het eind'],
    ],
    'pass_average' => 3.0,

    // Video's van studenten: privé opslag op de server (zoals de keten), maximaal aantal kilobytes per bestand.
    'video_disk' => env('ACADEMY_VIDEO_DISK', 'local'),
    'video_max_kb' => 512000,
];
