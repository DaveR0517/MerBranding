<?php

use App\Models\MessageTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * Welkomst- en uitnodigingsmail: inloggegevens erin (inloggen met het e-mailadres, wachtwoord zelf kiezen via de knop).
 * Bestaande sjablonen alleen bijwerken als het team de tekst nog niet zelf aanpaste (nog de oude standaardtekst).
 */
return new class extends Migration
{
    private const OUD = [
        'c-welkom' => ['subject' => 'Welkom bij MEBRANDING', 'email' => '{"aanhef": "Hoi {voornaam},", "alineas": ["Je bent goedgekeurd als creator. Je start op niveau {niveau}.", "Rond je checklist af: koppelingen, uitbetaalgegevens en je werkgebied. Daarna kun je uitgenodigd worden."], "knop": "Checklist afronden", "deeplink": "CreatorOverzicht/swelkom"}'],
        'g-uitnodiging' => ['subject' => null, 'email' => '{"aanhef": "Hoi {voornaam},", "alineas": ["{naam} nodigt je uit om mee te werken in het portaal van {zaak} als {rol}.", "De uitnodiging is 7 dagen geldig."], "knop": "Uitnodiging accepteren", "deeplink": "Inloggen"}'],
    ];

    private const NIEUW = [
        'c-welkom' => ['subject' => 'Je account bij MEBRANDING staat klaar', 'email' => '{"aanhef": "Hoi {voornaam},", "alineas": ["Goed nieuws: je bent goedgekeurd als creator bij MEBRANDING. Je start op niveau {niveau}.", "Je inloggegevens: je logt in met dit e-mailadres, {email}. Je wachtwoord kies je zelf via de knop hieronder. Daarna ben je direct ingelogd. De link werkt 7 dagen.", "Rond daarna je checklist af: profiel, raamovereenkomst en uitbetaalgegevens. Dan kun je uitgenodigd worden voor klussen.", "Vragen? Beantwoord deze mail, dan helpen we je."], "knop": "Wachtwoord kiezen en inloggen", "deeplink": "CreatorOverzicht/swelkom"}'],
        'g-uitnodiging' => ['subject' => null, 'email' => '{"aanhef": "Hoi {voornaam},", "alineas": ["{naam} nodigt je uit om mee te werken in het portaal van {zaak} als {rol}.", "Je inloggegevens: je logt in met dit e-mailadres, {email}. Je wachtwoord kies je zelf via de knop hieronder. Daarna ben je direct ingelogd.", "De uitnodiging is 7 dagen geldig."], "knop": "Wachtwoord kiezen en inloggen", "deeplink": "Inloggen"}'],
    ];

    public function up(): void
    {
        foreach (self::NIEUW as $sleutel => $nieuw) {
            $sjabloon = MessageTemplate::where('key', $sleutel)->first();
            if (! $sjabloon || $sjabloon->email != json_decode(self::OUD[$sleutel]['email'], true)) {
                continue; // bestaat nog niet (de seeder maakt hem nieuw aan) of door het team aangepast
            }
            $sjabloon->email = json_decode($nieuw['email'], true);
            if ($nieuw['subject'] && $sjabloon->subject === self::OUD[$sleutel]['subject']) {
                $sjabloon->subject = $nieuw['subject'];
            }
            $sjabloon->save();
        }
    }

    public function down(): void
    {
        // Tekstwijziging: niet terugdraaien, het team kan de tekst aanpassen in Berichtsjablonen.
    }
};
