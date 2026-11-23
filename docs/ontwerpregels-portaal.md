# Ontwerpregels portaal

Wens van Dave (22-11-2026): overzicht, structuur, georganiseerd. Strak uitgelijnd, niet te veel scrollen, goed op elke schermmaat.

## Regels
1. **Eén bouwdoos.** Elk scherm gebruikt `resources/js/components/pagina` (Pagina, PaginaKop, Sectie, Kaart, KaartRaster, Kerncijfer, Rij, MeerLijst, Uitklap, Tabs, TweeKolommen, Leeg). Geen eigen paginakoppen, containers of kaartstijlen per module.
2. **Eén breedte.** `Pagina` is max 1280 px breed (formulieren en leesschermen: `smal`, inhoud max 768 px, links op dezelfde lijn, niet gecentreerd). Linkerkant van kop en inhoud valt altijd op dezelfde lijn.
2b. **Kolommen volgen de inhoud, niet het scherm.** Zijkolommen (`TweeKolommen`, hoofd plus vaste zijkolom) gaan naast elkaar vanaf een inhoudsbreedte van 896 px (`@4xl:`), zodat de zijbalk ze op een laptop niet platdrukt. In een rij die mag doorlopen krijgt het tekstdeel een minimumbreedte (`min-w-48 flex-1`), zodat knoppen eronder vallen in plaats van de tekst te pletten.
3. **Eén kop.** `PaginaKop`: titel in gewone hoofd- en kleine letters (geen kapitalen), korte omschrijving, acties rechts; op de telefoon de acties onder de titel.
4. **Ritme.** Ruimte tussen blokken altijd `space-y-5` (telefoon) / `space-y-6` (groot); kaarten `p-4 md:p-5`, compacte kaarten en rijen `p-3 md:p-4`. Raster altijd via `KaartRaster` (1, 2, dan 3 of 4 kolommen).
5. **Niet te veel scrollen.** Richtwaarde: een overzichtsscherm is op een laptop hooguit 2 schermhoogtes, op de telefoon hooguit 4. Lange lijsten: `MeerLijst` (eerste 8), tabbladen (`Tabs`) of filters. Details die niet altijd nodig zijn: `Uitklap`. Op een groot scherm de ruimte benutten met `TweeKolommen` of een raster in plaats van alles onder elkaar.
6. **Lijsten compact.** Een item is een `Rij` (titel, één regel eronder, status of actie rechts), geen hoge kaart met veel lucht. Tabellen op een groot scherm, rijen op de telefoon.
7. **Kleur met mate.** Donkere merkkaart (`Kaart nadruk`) alleen voor het ene belangrijkste blok per scherm (bijv. tegoed). Statuskleuren via `StatusBadge`.
8. **Elke schermmaat.** Controleren op 390, 820 en 1440 breed: geen horizontale scroll, niets afgekapt, knoppen minimaal 40 px hoog op de telefoon.
