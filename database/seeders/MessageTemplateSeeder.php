<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

/**
 * Berichtsjablonen uit database/data/berichtsjablonen.json (concept 18-11-2026). Veilig om vaker te draaien:
 * alleen ontbrekende sjablonen worden aangemaakt, zodat wijzigingen van het team (Instellingen, Berichtsjablonen) blijven staan.
 */
class MessageTemplateSeeder extends Seeder
{
    /** Afwijkingen van het bestand, met reden. */
    private const KANALEN = [
        // Dave (22-11-2026): bij goedkeuring krijgt de ondernemer de video te zien plus een melding, ook per e-mail.
        'o-goedgekeurd' => ['inapp' => 'verplicht', 'email' => 'aan', 'push' => 'uit'],
    ];

    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/berichtsjablonen.json')), true);

        foreach ($data['sjablonen'] as $s) {
            $sjabloon = MessageTemplate::firstOrCreate(['key' => $s['id']], [
                'role' => $s['rol'],
                'event' => $s['gebeurtenis'],
                'channels' => self::KANALEN[$s['id']] ?? $s['kanalen'],
                'subject' => $s['onderwerp'],
                'email' => $s['email'] ?? null,
                'inapp' => $s['inapp'] ?? null,
                'push' => $s['push'] ?? null,
            ]);

            // Pushtekst kwam later: alleen aanvullen waar hij nog leeg is, wijzigingen van het team blijven staan.
            if ($sjabloon->push === null && ! empty($s['push'])) {
                $sjabloon->update(['push' => $s['push']]);
            }
        }
    }
}
