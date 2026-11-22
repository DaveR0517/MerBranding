# MEBRANDING demo als app op een eigen domein

Deze map is de webapp-versie van de werkende demo. Zet de inhoud op een eigen domein, dan kun je de demo installeren als app op iPhone, iPad, Android, Mac en Windows.

## Wat er in deze map zit

| Bestand | Waarvoor |
|---|---|
| `index.html` | De hele app in één pagina |
| `manifest.webmanifest` | Naam, kleuren, iconen en schermafbeeldingen voor de installatie |
| `sw.js` | Service worker: bewaart de app-schil, zodat hij ook zonder internet opent |
| `icons/` | App-iconen (192, 512, maskable 192 en 512 op aubergine, apple-touch-icon 180, svg, favicon) |
| `screenshots/` | Schermafbeeldingen die Chrome en Edge tonen in het installatievenster |

## Eerlijk vooraf: artifact-link of eigen domein

- **Binnen de claude.ai-link** (het artifact) werkt de demo live tussen apparaten: wat Sanne op de laptop doet, ziet Sophie direct op haar telefoon. Maar "Zet op beginscherm" maakt daar alleen een snelkoppeling naar claude.ai, geen echte app. Installeren en offline werken kan daar niet: claude.ai laat geen service worker toe in een artifact.
- **Op een eigen domein** (deze map) is het een echte app: eigen icoon, opent zonder adresbalk, start ook zonder internet. Maar de gedeelde database van claude.ai is daar niet beschikbaar. De demo draait dan per apparaat: alles blijft op dat apparaat staan (ook na sluiten), maar acties verschijnen niet op een ander apparaat. Bovenin staat dan "Lokale demo".
- Wil je allebei (echte app én live tussen apparaten), dan is een eigen backend nodig, bijvoorbeeld een kleine Laravel-API met een database. Dat is de stap na deze demo.

**Advies voor het gesprek:** laat de keten tussen twee apparaten zien via de artifact-link. Laat de installatie zien op een telefoon of iPad met de versie op het eigen domein.

## Online zetten

Voorwaarden: HTTPS (verplicht voor een service worker), en alle bestanden in dezelfde map. Een submap mag ook, bijvoorbeeld `https://demo.mebranding.nl/app/`: alle paden zijn relatief.

### Laravel Forge

1. Maak in Forge een nieuwe site aan, bijvoorbeeld `demo.mebranding.nl`, type **Static HTML**, web directory `/public`.
2. Zet de DNS van het subdomein (A-record) naar het IP van de Forge-server.
3. Upload de inhoud van deze map naar `/home/forge/demo.mebranding.nl/public/` (via een Git-repository met deploy, of via SFTP).
4. Zet SSL aan: tabblad **SSL**, **Let's Encrypt**.
5. Voeg in **Files, Edit Nginx Configuration** binnen het `server`-blok toe, zodat updates direct doorkomen en het manifest goed wordt herkend:

```nginx
location = /sw.js { add_header Cache-Control "no-cache"; }
location = /index.html { add_header Cache-Control "no-cache"; }
location = /manifest.webmanifest { default_type application/manifest+json; add_header Cache-Control "no-cache"; }
```

### Statische host (Netlify, Cloudflare Pages, Vercel, GitHub Pages)

Sleep deze map in het uploadvenster of koppel een repository. HTTPS staat daar standaard aan. Optioneel bij Netlify of Cloudflare Pages een bestand `_headers` erbij:

```
/sw.js
  Cache-Control: no-cache
/index.html
  Cache-Control: no-cache
```

## Installeren

- **iPhone en iPad (Safari):** open de link, tik op Deel (vierkant met pijl omhoog) en kies **Zet op beginscherm**. De app krijgt het MEBRANDING-icoon en opent schermvullend. De app toont zelf een kleine tip met deze uitleg; die kun je wegklikken en komt dan niet terug.
- **Android (Chrome):** de app toont een tip met de knop **Installeren**. Of via het menu (drie puntjes), **App installeren**.
- **Mac en Windows (Chrome of Edge):** de tip met **Installeren** in de app, of het installatie-icoon rechts in de adresbalk.

## Bijwerken

Upload een nieuwe `index.html` en `sw.js` (de bouwstap zet een nieuwe versie in `sw.js`). Een geïnstalleerde app haalt de nieuwe versie op bij de volgende keer openen met internet.

## Goed om te weten

- De lettertypen komen van Google Fonts en worden na het eerste bezoek bewaard voor offline gebruik.
- "Demo opnieuw beginnen" in het demo-paneel zet de lokale demo terug naar de beginstand.
- In deze repository staan de bestanden in `demo/public`. De bron van de testversie zit in de overdrachtsmap (`prototype/testversie-bron/`).

## Testversie (19-11-2026): schermen.html
De testversie bestaat uit twee pagina's: `index.html` (de app met inlog, rollen en de gedeelde demo) en `schermen.html` (het klikbare prototype, ruim 6 MB). De app toont `schermen.html` in een venster voor alle schermen die nog een voorbeeld zijn. Zet beide bestanden in dezelfde map. De service worker bewaart `schermen.html` apart, zodat het nooit de app-pagina vervangt.
