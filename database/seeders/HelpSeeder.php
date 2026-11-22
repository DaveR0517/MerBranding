<?php

namespace Database\Seeders;

use App\Models\HelpArtikel;
use Illuminate\Database\Seeder;

/**
 * Eerste artikelen van het helpcentrum (module communicatie). Veilig om vaker te draaien: bijgewerkt op slug.
 * Termijnen en bedragen die nog VOORSTEL zijn, staan zo ook in de tekst.
 */
class HelpSeeder extends Seeder
{
    public function run(): void
    {
        $niveaus = collect(config('mebranding.levels'))->map(fn ($l) => "{$l['name']} {$l['points']}")->implode(', ');
        $uren = config('mebranding.timers.invitation_hours');
        $dagen = config('mebranding.timers.content_deadline_days');
        $meten = config('mebranding.timers.measure_days');
        $annuleren = config('mebranding.timers.late_cancel_hours');
        $mb = config('mebranding.video_max_mb');

        $artikelen = [
            [
                'slug' => 'hoe-werkt-een-samenwerking',
                'title' => 'Hoe werkt een samenwerking?',
                'category' => 'Samenwerkingen',
                'audiences' => ['ondernemer', 'creator', 'team'],
                'body' => <<<MD
Een samenwerking loopt van aanvraag tot afgerond. Je ziet altijd in welke stap hij staat en wie aan zet is.

1. **Aanvraag.** De ondernemer kiest een vestiging, schrijft een briefing en kiest het niveau van de creator. De punten worden direct gereserveerd.
2. **Creator zoeken.** Het team nodigt een creator uit die past. De creator heeft {$uren} uur om te reageren (voorstel).
3. **Creator gevonden.** De creator tekent het contract voor deze klus en kiest zelf een tijdvak binnen de bezoektijden. Dat staat direct vast.
4. **Bezoek.** De creator checkt in bij de zaak, beleeft wat in de briefing staat en filmt.
5. **Video maken en aanleveren.** De creator levert de video binnen {$dagen} dagen na het bezoek aan (voorstel).
6. **Controle.** Het team kijkt de video na. De ondernemer kan meekijken. Is er iets aan te passen, dan krijgt de creator feedback op het juiste moment in de video.
7. **Goedgekeurd en live.** Na goedkeuring plaatst de creator de video op de afgesproken datum op het eigen account en koppelt de link.
8. **Meten en afronden.** We meten {$meten} dagen. Daarna is de samenwerking afgerond en kan de ondernemer een beoordeling geven.

Vragen over een samenwerking stel je in **Berichten**, in het gesprek van die samenwerking.
MD,
            ],
            [
                'slug' => 'tegoed-en-punten',
                'title' => 'Hoe werken tegoed en punten?',
                'category' => 'Tegoed en facturen',
                'audiences' => ['ondernemer', 'team'],
                'body' => <<<MD
Bij je abonnement hoort elke maand een tegoed in punten. Een samenwerking kost punten, afhankelijk van het niveau van de creator: {$niveaus} punten (voorstel).

- **Reserveren.** Bij een aanvraag zetten we de punten opzij. Je ziet ze als gereserveerd.
- **Verbruiken.** Zodra de samenwerking doorgaat, zijn de punten verbruikt.
- **Vrijgeven.** Annuleer je op tijd, of vinden we geen creator, dan komen de punten terug in je tegoed.
- **Laat annuleren.** Annuleer je korter dan {$annuleren} uur voor het bezoek, dan zijn de punten verbruikt (voorstel).

Op je overzicht zie je altijd hoeveel punten er beschikbaar en gereserveerd zijn. Klopt er iets niet? Stel een vraag hieronder, dan zoeken we het uit.
MD,
            ],
            [
                'slug' => 'reclamevermelding',
                'title' => 'Reclamevermelding: wat moet erbij?',
                'category' => "Video's en rechten",
                'audiences' => ['creator', 'ondernemer', 'team'],
                'body' => <<<'MD'
Elke video van een samenwerking is reclame. Kijkers moeten dat direct kunnen zien. Daarom vragen we drie dingen:

1. **In de tekst.** Begin je bijschrift met "Betaalde samenwerking met" en de naam van de zaak.
2. **Het label van het platform.** Zet bij Instagram en TikTok de instelling voor betaalde samenwerking aan.
3. **In beeld.** Vermeld het ook in de video zelf, bijvoorbeeld met een korte tekst in het begin.

Bij het aanleveren vink je aan dat je dit gedaan hebt. Het team controleert het. Ontbreekt de vermelding, dan krijg je feedback en lever je een nieuwe versie aan.
MD,
            ],
            [
                'slug' => 'video-aanleveren',
                'title' => 'Hoe lever ik mijn video aan?',
                'category' => "Video's en rechten",
                'audiences' => ['creator', 'team'],
                'body' => <<<MD
Na je bezoek open je de klus en tik je op **Video aanleveren**.

- Upload je video als mp4, mov of webm, tot {$mb} MB.
- Schrijf het bijschrift zoals je het gaat plaatsen, met de reclamevermelding.
- Vink aan dat het label van het platform en de vermelding in beeld erin zitten.

Lever aan binnen {$dagen} dagen na je bezoek (voorstel). Het team controleert je video meestal dezelfde werkdag. Krijg je feedback, dan zie je per moment in de video wat er anders moet. Je levert dan een nieuwe versie aan.

Na goedkeuring krijg je de datum waarop je de video plaatst. Plak daarna de link van je post in de klus.
MD,
            ],
            [
                'slug' => 'uitbetaling',
                'title' => 'Wanneer krijg ik mijn vergoeding?',
                'category' => 'Vergoedingen',
                'audiences' => ['creator', 'team'],
                'body' => <<<'MD'
Elke samenwerking geeft je een gratis bezoek. Creators met niveau **Platinum** of **Elite** krijgen daarnaast een vergoeding, betaald door MEBRANDING.

- Je vergoeding staat klaar zodra je video live staat en je de link hebt gekoppeld.
- We betalen uit op de eerstvolgende vrijdag, via onze betaalpartij.
- Vul je uitbetaalgegevens en je btw-status in bij je account. Zonder die gegevens kunnen we niet uitbetalen.

De bedragen per niveau zie je bij je uitnodiging en in je overzicht onder Vergoedingen. Is een betaling niet binnen? Stel een vraag hieronder en noem de samenwerking.
MD,
            ],
            [
                'slug' => 'account-en-wachtwoord',
                'title' => 'Account en wachtwoord',
                'category' => 'Account',
                'audiences' => ['ondernemer', 'creator', 'team'],
                'body' => <<<'MD'
**Eerste keer inloggen.** Je krijgt een uitnodiging per e-mail. Via de knop kies je zelf een wachtwoord.

**Wachtwoord vergeten.** Klik op de inlogpagina op "Wachtwoord vergeten" en vul je e-mailadres in. Je krijgt een link om een nieuw wachtwoord te kiezen.

**Extra beveiliging.** In je accountinstellingen zet je tweestapsverificatie aan of voeg je een passkey toe.

**Gegevens wijzigen.** Je naam en e-mailadres pas je ook aan in je accountinstellingen.

Deel je wachtwoord nooit, ook niet met het team. Wij vragen er nooit om.
MD,
            ],
        ];

        foreach ($artikelen as $i => $artikel) {
            HelpArtikel::firstOrCreate(['slug' => $artikel['slug']], $artikel + ['position' => ($i + 1) * 10]); // bestaande artikelen (bewerkt door het team) blijven staan
        }
    }
}
