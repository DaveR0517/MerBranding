<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

/**
 * Berichtsjablonen uit database/data/berichtsjablonen.json, de enige bron voor teksten, kanalen en mailvorm.
 * Veilig om vaker te draaien: nieuwe sjablonen worden aangemaakt, bestaande alleen bijgewerkt als het team ze niet
 * zelf heeft aangepast (de inhoud is nog gelijk aan de vorige standaardversie, standaard_hash).
 */
class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/berichtsjablonen.json')), true);

        foreach ($data['sjablonen'] as $s) {
            $inhoud = [
                'role' => $s['rol'],
                'event' => $s['gebeurtenis'],
                'vorm' => $s['vorm'] ?? 'standaard',
                'channels' => $s['kanalen'],
                'subject' => $s['onderwerp'],
                'email' => $s['email'] ?? null,
                'inapp' => $s['inapp'] ?? null,
                'push' => $s['push'] ?? null,
            ];
            $sjabloon = MessageTemplate::firstWhere('key', $s['id']);

            if (! $sjabloon) {
                MessageTemplate::create(['key' => $s['id'], ...$inhoud, 'standaard_hash' => MessageTemplate::vingerafdruk($inhoud)]);
            } elseif ($sjabloon->standaard_hash === null || $sjabloon->standaard_hash === MessageTemplate::vingerafdruk($sjabloon->only(array_keys($inhoud)))) {
                $sjabloon->update([...$inhoud, 'standaard_hash' => MessageTemplate::vingerafdruk($inhoud)]);
            }
        }
    }
}
