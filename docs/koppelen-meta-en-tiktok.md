# Eigen kanalen koppelen: Meta (Instagram) en TikTok

Stappenplan om het eigen Instagram- en TikTok-account van MEBRANDING aan het portaal te koppelen (Team, MeStudio, Eigen kanalen).
Het portaal gebruikt alleen de officiële API's. Zolang de sleutels ontbreken, toont het scherm "Nog niet gekoppeld" met een checklist.

## Adressen

| Waarvoor | Testadres (Forge) | Later (eigen domein) |
|---|---|---|
| Instagram redirect-URI | https://mebranding.on-forge.com/app/team/kanalen/instagram/terug | https://app.mebranding.nl/team/kanalen/instagram/terug |
| Instagram webhook (callback) | https://mebranding.on-forge.com/app/webhooks/instagram | https://app.mebranding.nl/webhooks/instagram |
| Instagram "Deauthorize callback" | https://mebranding.on-forge.com/app/webhooks/instagram/ontkoppeld | https://app.mebranding.nl/webhooks/instagram/ontkoppeld |
| Instagram "Data deletion request" | https://mebranding.on-forge.com/app/webhooks/instagram/verwijderen | https://app.mebranding.nl/webhooks/instagram/verwijderen |
| TikTok redirect-URI | https://mebranding.on-forge.com/app/team/kanalen/tiktok/terug | https://app.mebranding.nl/team/kanalen/tiktok/terug |
| TikTok webhook (callback) | https://mebranding.on-forge.com/app/webhooks/tiktok | https://app.mebranding.nl/webhooks/tiktok |

Zet bij een domeinwissel beide adressen erin (oud en nieuw), dan blijft koppelen werken tijdens de overstap.
Het scherm Eigen kanalen toont dezelfde adressen voor de omgeving waarin je kijkt, met een kopieerknop.

## Env-variabelen in Forge

| Variabele | Waar vind je hem |
|---|---|
| `INSTAGRAM_CLIENT_ID` | Meta app, Instagram, API setup with Instagram login: **Instagram app ID** (niet het Meta-app-ID bovenaan) |
| `INSTAGRAM_CLIENT_SECRET` | Zelfde plek: **Instagram app secret** |
| `INSTAGRAM_WEBHOOK_VERIFY_TOKEN` | Zelf verzinnen: een lange willekeurige tekst. Dezelfde waarde vul je bij Meta in als "Verify token" |
| `INSTAGRAM_WEBHOOK_SECRET` | Optioneel. Alleen invullen als Meta webhooks met een ander geheim ondertekent dan het Instagram app secret (dan meldt het logboek "handtekening klopt niet") |
| `INSTAGRAM_GRAPH_VERSION` | Optioneel, standaard `v23.0` (VOORSTEL). Alleen wijzigen als Meta een versie uitfaseert |
| `TIKTOK_CLIENT_KEY` | TikTok for Developers, je app: **Client key** |
| `TIKTOK_CLIENT_SECRET` | Zelfde plek: **Client secret** |

Na het invullen in Forge: `php artisan config:cache` draait bij de deploy. Daarna verschijnt in het portaal de knop Koppelen.
Let op: de queue (Horizon) moet draaien voor "Plaatsen op Instagram", en de scheduler (`schedule:run` elke minuut) voor het uurlijks ophalen.

## Meta Developers (Instagram)

1. Ga naar developers.facebook.com, Mijn apps, App maken. Kies als gebruik "Overige" en daarna het app-type **Bedrijf** (Business). Koppel de app aan de Business Manager van MEBRANDING.
2. Voeg in het dashboard het product **Instagram** toe en kies **API setup with Instagram login** (Instagram API met Instagram Login voor bedrijven). Facebook Login is niet nodig.
3. Het Instagram-account van MEBRANDING moet een **professioneel account** zijn (Bedrijf of Creator; instelbaar in de Instagram-app).
4. **Instagram business login instellen** (stap "Set up Instagram business login"):
   - OAuth redirect URI: het Instagram-redirectadres uit de tabel.
   - Deauthorize callback URL en Data deletion request URL: de twee adressen uit de tabel.
5. **Webhooks** (stap "Configure webhooks"):
   - Callback URL: het Instagram-webhookadres uit de tabel.
   - Verify token: dezelfde waarde als `INSTAGRAM_WEBHOOK_VERIFY_TOKEN`.
   - Klik Verify and save. Meta roept het adres dan aan; het portaal antwoordt alleen als het token klopt.
   - Abonneer op de velden **messages** en **comments**. Het portaal zet bij elk koppelen zelf het abonnement voor het account aan.
6. **Rechten** (worden bij het koppelen gevraagd):
   - `instagram_business_basic`: profiel en posts
   - `instagram_business_manage_insights`: weergaven, bereik en andere cijfers
   - `instagram_business_manage_comments`: reacties lezen en beantwoorden
   - `instagram_business_manage_messages`: berichten (DM's) lezen en beantwoorden
   - `instagram_business_content_publish`: plaatsen vanuit MeStudio
7. **Testgebruikers en App Review**:
   - Zolang de app in ontwikkelmodus staat, werkt hij alleen voor accounts met een rol in de app. Voeg het Instagram-account van MEBRANDING toe onder App-rollen, Rollen, **Instagram Testers** en accepteer de uitnodiging in Instagram (Instellingen, Website-toestemmingen, Apps en websites, Testuitnodigingen).
   - Voor alleen het eigen account is Standard Access genoeg voor de API-aanroepen. Webhooks (binnenkomende berichten en reacties) komen volgens Meta pas binnen als de app **Live** staat. Live zetten vraagt een privacybeleid-URL, een app-icoon, een categorie en de verwijder-URL hierboven; Meta kan om bedrijfsverificatie vragen. Niet getest: controleer in het dashboard of Meta voor jouw app ook Advanced Access eist voor webhooks.
   - Voor koppelingen van creators en ondernemers (later) is **Advanced Access via App Review** nodig voor elk van de rechten hierboven. Meta vraagt per recht een screencast van het gebruik en een toelichting. Reken op enkele dagen tot weken.
8. Vul de env-variabelen in Forge in, deploy, en klik in het portaal op Instagram koppelen. Log in met het MEBRANDING-account en vink alle rechten aan.

Goed om te weten:
- Het token geldt 60 dagen; het portaal verlengt het automatisch vanaf dag 50. Gaat dat mis, dan krijgt het team een melding.
- Cijfers van Meta kunnen tot 48 uur achterlopen. "Impressions" bestaat niet meer; Meta gebruikt nu "views" (weergaven).
- Berichten beantwoorden kan alleen binnen 24 uur na het laatste bericht van de ander (regel van Meta). Het scherm toont hoe lang nog.
- Plaatsen: Meta haalt het bestand zelf op via een tijdelijke link (1 uur geldig). Afbeeldingen moeten JPG zijn, video's MP4. Hooguit 50 posts per 24 uur via de API.

## TikTok for Developers

1. Ga naar developers.tiktok.com, Manage apps, Connect an app. Kies het MEBRANDING-account of maak een organisatie aan.
2. Vul de basisgegevens in: app-icoon, naam, categorie, omschrijving, en de URL's van de **Terms of Service** en het **Privacy Policy** (op mebranding.nl). TikTok vraagt de websites te verifiëren (URL-prefix of DNS).
3. Voeg de producten toe:
   - **Login Kit** (platform Web): redirect-URI uit de tabel. TikTok accepteert alleen https.
   - **Display API**: profiel en video's met cijfers.
   - **Content Posting API**: nu alleen voorbereid (recht `video.publish`). Zonder goedgekeurde audit plaatst TikTok alleen privé.
   - **Webhooks**: callback-adres uit de tabel. Het portaal controleert de handtekening (TikTok-Signature) met het client secret.
4. **Scopes** aanvragen: `user.info.basic`, `user.info.profile`, `user.info.stats`, `video.list` (en later `video.publish`).
5. **Sandbox en review**: in de Sandbox kun je meteen testen met toegevoegde testaccounts (Target users). Voeg het TikTok-account van MEBRANDING toe. Voor productie dien je de app in voor review met een korte video van de koppeling; reken op enkele dagen.
6. Vul `TIKTOK_CLIENT_KEY` en `TIKTOK_CLIENT_SECRET` in Forge in, deploy, en klik in het portaal op TikTok koppelen.

Goed om te weten:
- TikTok heeft **geen API voor privéberichten**. Berichten blijven in de TikTok-app; het portaal zegt dat ook.
- Het toegangstoken geldt 24 uur en wordt elk uur zo nodig verlengd; het vernieuwtoken geldt een jaar. Na een jaar of na intrekken: opnieuw koppelen.
- Reacties op TikTok kun je via de Display API niet lezen, alleen het aantal.
- Omslagafbeeldingen van TikTok verlopen na ongeveer 6 uur; het uurlijks ophalen ververst ze.

## Wat het portaal doet zodra de sleutels er zijn

- Elk uur (taak `eigen-kanalen-verversen`): tokens verlengen, profiel, posts en video's, cijfers per post (Instagram: weergaven, bereik, likes, reacties, opslagen, delen, interacties), accountcijfers per dag en Instagram-gesprekken.
- Direct via webhooks: nieuwe Instagram-berichten en reacties, met een melding aan het team (recht `kanalen.beheren`).
- Rechten: alleen medewerkers met `kanalen.beheren` (sjablonen Accountmanager en Sales) en de Super Admin.
