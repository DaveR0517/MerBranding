<?php

/*
| Gmail van MEBRANDING (Google Workspace) in het portaal, via een service-account met domeinbrede delegatie.
| De sleutel zet Dave zelf in Forge (nooit in Git): GMAIL_SERVICE_ACCOUNT = de JSON-sleutel in base64.
| GMAIL_POSTVAKKEN: komma-gescheiden; een persoonlijk postvak met het portaalaccount erachter: postvak=portaal-e-mailadres.
| Een postvak zonder portaalaccount (support@) is gedeeld en vraagt geen akkoord.
*/
return [
    'service_account' => env('GMAIL_SERVICE_ACCOUNT'),

    'scopes' => ['https://www.googleapis.com/auth/gmail.modify', 'https://www.googleapis.com/auth/gmail.send'],

    'postvakken' => collect(explode(',', (string) env('GMAIL_POSTVAKKEN', '')))
        ->map(fn ($p) => trim($p))->filter()
        ->mapWithKeys(function ($p) {
            [$postvak, $account] = array_pad(explode('=', $p, 2), 2, null);

            return [strtolower(trim($postvak)) => $account ? strtolower(trim($account)) : null];
        })->all(),
];
