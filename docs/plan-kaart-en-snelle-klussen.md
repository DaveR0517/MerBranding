# Plan: kaart en snelle klussen

Status (besluit Dave, 24 nov): fase 1, de kaart voor team en eigenaren, staat live. De creatorkant (fase 2 en 3) staat niet aan en wordt gebouwd op branch `creator-kaart`: eerst genoeg creators per regio. Idee van Dave: zaken en klussen op een kaart, en creators die in de buurt zijn een snelle klus kunnen aannemen.

## Fase 1: kaart voor het team (live)

- Team, Relaties, Kaart (`/team/kaart`, recht `bedrijven.bekijken`).
- Op de kaart: vestigingen van aangesloten zaken (klant, eigen zaak, zonder abonnement), nieuwe aanmeldingen van ondernemers (gestippeld) en per vestiging het aantal open klussen (rode teller).
- Filters per soort en "alleen met open klus"; lijst naast de kaart; klik vliegt naar de stip; popup met adres, open klussen en een link naar het bedrijf.
- Plek op de kaart via de PDOK Locatieserver van het Kadaster (gratis, geen sleutel, alleen Nederland). Ondergrond: pastelkaart van PDOK.
- Automatisch: nieuwe of verhuisde vestiging en een nieuwe aanmelding van een ondernemer worden op de achtergrond opgezocht. Eenmalig voor bestaande gegevens: `php artisan mebranding:geocoderen`.
- Website, Kennismaken: nieuw verplicht veld Plaats en optioneel Adres van de zaak, zodat een aanmelding op de kaart kan.
- Creators staan niet op de teamkaart: hun woonplaats blijft privé.

## Fase 2: kaart voor creators, "Klussen in de buurt"

Gebouwd op branch `creator-kaart`, achter de schakelaar `mebranding.creator_kaart` (standaard uit):
- pop-up "Ben je beschikbaar voor een klus in de buurt?" bij het openen van de app op de telefoon: 2 uur, 4 uur, rest van de dag of nu niet; één keer per keer openen, na het webapp-venster;
- plek één keer opgevraagd met toestemming, afgerond op ongeveer 100 meter, alleen binnen Nederland; zonder toestemming telt de woonplaats;
- balk "Je bent beschikbaar tot ..." met Stoppen; de planner wist verlopen beschikbaarheid en de plek elk kwartier;
- groene stippen voor beschikbare creators op de teamkaart.
Nog te bouwen: de creatorkaart zelf met klussen in de buurt en de pushmelding.

- Creator opent de kaart in de webapp en ziet open klussen in de buurt: zaak, wat er te doen is, beloning, afstand.
- Plek van de creator: alleen op het moment dat de kaart open is, na toestemming van de telefoon. Niet opgeslagen als spoor.
- Eerlijk over "altijd de locatie zichtbaar": een webapp kan de locatie niet op de achtergrond volgen (iPhone en Android staan dat niet toe), en voor de AVG is permanent volgen zwaar te verantwoorden. Alternatief dat hetzelfde doel haalt:
  - knop **"Ik ben beschikbaar"**: de creator deelt een paar uur lang de plek, zichtbaar voor het team en voor het matchen van snelle klussen; daarna vervalt het vanzelf;
  - anders telt de woonplaats met de reisafstand uit het profiel (`radius_km` bestaat al).
- Pushmelding "Snelle klus bij jou in de buurt" naar creators binnen de straal (webapp-meldingen bestaan al).

## Fase 3: snelle klus (spoedopdracht)

Voor zaken die snel content nodig hebben, bijvoorbeeld een winkel met een nieuw kledingstuk.

1. Ondernemer plaatst een **snelle klus**: wat (kort), foto van het product, wanneer (vandaag, morgen of binnen een tijdvak), beloning.
2. De klus verschijnt op de creatorkaart en creators binnen de straal krijgen een pushmelding.
3. Een creator neemt hem aan met één tik en tekent het kluscontract in dezelfde stap.
4. Daarna de bestaande keten: inchecken bij de zaak, filmen, aanleveren, controle door het team, goedkeuren, live.
5. Het team ziet snelle klussen op de teamkaart en kan ingrijpen (iemand anders kiezen, annuleren).

## Besluit Dave (24 nov): wie het eerst aanneemt, krijgt de klus; aannemen is bindend

- Eén snelle klus is één plek (vraagt de zaak er twee, dan zijn het twee plekken). Zodra een creator aanneemt, is de klus weg voor de rest: geen tien creators voor de deur.
- Op de kaart zien creators alleen de buurt; adres en tijdvak krijgt pas wie aanneemt. Inchecken kan alleen de creator die de klus heeft.
- Aannemen is bindend: aannemen en het kluscontract tekenen is één tik, met duidelijk erbij "Je moet dan ook echt komen".
- Niet komen kost punten in het klassement: dezelfde regel als bij een gewone samenwerking (Score: niet gekomen, nu -50 op betrouwbaarheid, werkwaarde), één bron. Te laat komen: de bestaande regel te laat.
- Spreiden: maximaal één open snelle klus per creator tegelijk, zodat niet één creator alles wegkaapt.
- Voorstel, nog te bevestigen: per ongeluk aangenomen? Binnen 5 minuten kosteloos teruggeven. Niet ingecheckt 30 minuten na het einde van het tijdvak: het team krijgt een taak om "niet verschenen" vast te leggen.

## Keuzes voor Dave (voordat fase 3 gebouwd wordt)

1. ~~Wie krijgt de klus~~: besloten, wie het eerst aanneemt (zie hierboven).
2. **Prijs:** zelfde punten als een gewone samenwerking, of een spoedtoeslag in punten?
3. **Beloning creator:** gratis product of bezoek zoals nu, of altijd een vaste vergoeding bij een snelle klus?
4. **Wie mag:** alle niveaus, of vanaf een bepaald niveau (betrouwbaarheid bij spoed)?
5. **Straal:** standaard reisafstand uit het profiel, of een vaste straal voor snelle klussen (bijvoorbeeld 10 km)?
6. **Welke zaken:** iedere klant, of per bedrijf aan te zetten door het team?

## Grenzen (KWALITEIT, techniek en wet)

- AVG: locatie alleen met toestemming, alleen zolang nodig, uitleg in de privacyverklaring en in de app.
- EU-richtlijn platformwerk: uitleggen waarom een creator een klus te zien krijgt (afstand, niveau), zoals de matchlijst al doet.
- Reclamevermelding en alcoholregel gelden ook voor snelle klussen.
