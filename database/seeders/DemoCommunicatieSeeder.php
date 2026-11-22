<?php

namespace Database\Seeders;

use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadFase;
use App\Models\SupportVraag;
use App\Models\Uitnodigingslink;
use App\Models\User;
use App\Services\Berichten;
use App\Services\Samenwerking;
use App\Services\Support;
use Illuminate\Database\Seeder;

/**
 * Voorbeelddata voor berichten, support en sales. Draai na DemoSeeder. Idempotent: doet niets als er al berichten zijn.
 */
class DemoCommunicatieSeeder extends Seeder
{
    public function run(Samenwerking $keten, Berichten $berichten, Support $support): void
    {
        $this->call(HelpSeeder::class);

        $wie = fn (string $naam) => User::where('email', "{$naam}@".DemoSeeder::DOMEIN)->firstOrFail();
        [$sanne, $eva, $mirthe, $sophie] = [$wie('sanne'), $wie('eva'), $wie('mirthe'), $wie('sophie')];
        $bloesem = Company::where('name', 'Lunchroom Bloesem')->firstOrFail();

        if (! Collaboration::where('title', 'Wintersoep en appeltaart')->exists()) {
            $c = $keten->aanvragen($bloesem, $sanne, [
                'location_id' => $bloesem->locations()->where('name', 'Leeuwarden')->value('id'),
                'title' => 'Wintersoep en appeltaart', 'briefing' => 'Laat de wintersoep en de appeltaart zien, gezellig bij het raam.',
                'alcohol' => false, 'creator_level' => 'elite',
            ]);
            $berichten->sturen($c, $sanne, 'ondernemer', 'team_ondernemer', 'Hoi Eva, kan de creator in de video ook noemen dat we in december een kerstlunch hebben? Reserveren kan via onze site.');
            $berichten->sturen($c, $eva, 'team', 'team_ondernemer', 'Goed idee, Sanne. Ik zet het als aanvulling in de briefing. De creator noemt de kerstlunch aan het eind van de video.');

            $uitnodiging = $keten->uitnodigen($c, $sophie->creator, $eva);
            $keten->accepteren($uitnodiging, $sophie, 'Sophie de Vries', '127.0.0.1');
            $berichten->sturen($c->fresh(), $eva, 'team', 'team_creator', 'Welkom bij deze samenwerking, Sophie! Sanne vraagt of je aan het eind de kerstlunch in december wilt noemen, reserveren via de site van Bloesem.');
            $berichten->sturen($c->fresh(), $sophie, 'creator', 'team_creator', 'Ik heb er zin in! Ik neem mijn lamp mee voor de close-ups van de soep.');
        }

        if (SupportVraag::doesntExist()) {
            $support->indienen($sanne, 'ondernemer', ['subject' => 'Tweede gebruiker voor Bloesem Sneek', 'body' => 'Kan mijn bedrijfsleider in Sneek ook inloggen, met een eigen account?', 'priority' => 'normaal']);
            $v = $support->indienen($sophie, 'creator', ['subject' => 'Btw-status: wat vul ik in?', 'body' => 'Ik werk als particulier. Wat moet ik invullen bij btw-status?', 'priority' => 'hoog']);
            $support->antwoorden($v, $eva, 'Hoi Sophie, kies dan "particulier". Twijfel je, dan bellen we even.');
        }

        if (! Lead::where('company', 'Koffiebar Het Kompas')->exists()) {
            $code = Uitnodigingslink::voor($sanne)->code;
            $voorbeelden = [
                ['Jelle Bosma', 'Koffiebar Het Kompas', 'Leeuwarden', 'nieuw', 'Bellen', 2, ['referral_code' => $code]],
                ['Wietske Hoekstra', 'Bakkerij De Molen', 'Sneek', 'gebeld', 'Kennismaking plannen', 1, null],
                ['Pieter Zijlstra', 'Hotel Wadzicht', 'Harlingen', 'afspraak', 'Kennismaking in de zaak', 5, null],
                ['Anna de Boer', 'Restaurant Het Dok', 'Franeker', 'offerte', 'Nabellen over het voorstel', -1, null],
                ['Klaas Veenstra', 'IJssalon Klaas', 'Dokkum', 'verloren', null, null, null],
            ];
            foreach ($voorbeelden as [$naam, $bedrijf, $plaats, $fase, $actie, $over, $data]) {
                $lead = Lead::create([
                    'type' => Lead::TYPE_ONDERNEMER, 'status' => 'nieuw', 'name' => $naam, 'company' => $bedrijf, 'city' => $plaats,
                    'email' => strtolower(explode(' ', $naam)[0]).'@'.str($bedrijf)->slug().'.test', 'phone' => '06 1234 5678',
                    'message' => 'Graag meer weten over de pakketten.', 'data' => $data, 'consent_at' => now(), 'consent_version' => '0.1',
                ]);
                LeadFase::create(['lead_id' => $lead->id, 'stage' => $fase, 'owner_id' => $mirthe->id, 'next_action' => $actie, 'next_action_on' => $over === null ? null : today()->addDays($over)]);
            }
        }
    }
}
