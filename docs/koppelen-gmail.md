# Gmail van MEBRANDING koppelen (Google Workspace)

Het portaal leest de postvakken via een service-account met domeinbrede delegatie. Mail wordt live bij Google opgehaald en niet in het portaal bewaard. Te vinden onder MeStudio, Mail (en Eigen kanalen, tabblad Mail).

Wie ziet wat (besluit Dave, 27 nov): de eigenaren (Super Admin) zien alle postvakken. Een persoonlijk postvak van een ander pas nadat de eigenaar van dat postvak in het portaal op Akkoord klikte (vastgelegd met tijdstip, in te trekken). Iedereen ziet zijn eigen postvak. support@ is gedeeld.

## Eenmalig instellen (Workspace-beheerder)

1. **Google Cloud Console** (console.cloud.google.com), ingelogd met het beheerdersaccount:
   - nieuw project, bijvoorbeeld "MEBRANDING portaal";
   - API's en services, Bibliotheek: zet de **Gmail API** aan.
2. **Service-account**: IAM en beheer, Serviceaccounts, Serviceaccount maken (naam "portaal-gmail", geen rollen nodig).
   - Open het account, tabblad Sleutels, Sleutel toevoegen, JSON. Er wordt een bestand gedownload: dit is de sleutel, bewaar hem veilig en stuur hem nergens heen.
   - Noteer de **Unieke ID** (Client ID, een lang getal) van het service-account.
3. **Google Admin** (admin.google.com): Beveiliging, Toegangs- en gegevensbeheer, API-beheer, **Domeinbrede delegatie beheren**, Nieuw toevoegen:
   - Client ID: de Unieke ID uit stap 2;
   - OAuth-bereiken (precies zo, met komma):
     `https://www.googleapis.com/auth/gmail.modify,https://www.googleapis.com/auth/gmail.send`
4. **Forge** (site mebranding, Environment), twee regels toevoegen en opslaan, daarna deployen:
   - `GMAIL_POSTVAKKEN=davey@mebranding.nl=dave@mebranding.nl,taipan@mebranding.nl,eline@mebranding.nl,support@mebranding.nl`
     (achter het `=`: het e-mailadres waarmee die persoon in het portaal inlogt, alleen nodig als dat anders is dan het postvak; postvakken zonder portaalaccount, zoals support@, zijn gedeeld)
   - `GMAIL_SERVICE_ACCOUNT=` gevolgd door de JSON-sleutel in base64 op één regel. Maken op de Mac: `base64 -i sleutel.json | pbcopy` (staat dan op het klembord); op Windows (PowerShell): `[Convert]::ToBase64String([IO.File]::ReadAllBytes("sleutel.json")) | Set-Clipboard`.
5. Verwijder het gedownloade sleutelbestand van je computer.

Let op: gebruik het **hoofdadres** van een postvak, geen alias. Is davey@ een alias van dave@, zet dan dave@mebranding.nl als postvak.

## Controleren

- Eigen kanalen, Overzicht: kaart "Gmail (Google Workspace)" staat op Gekoppeld.
- MeStudio, Mail: support@ toont de inbox. Een melding "Google gaf geen toegang" betekent meestal dat stap 3 nog niet klaar is (kan tot een uur duren) of dat een postvak een alias is.
- Eline (en Taipan en Dave voor elkaar): op de pagina Mail eenmalig op Akkoord geven klikken.
