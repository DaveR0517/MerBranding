<?php

/*
| Module cijfers en geld (CLAUDE.md 7.2, 7.4, fase 6). Lees altijd via config('geld.*'):
| zo kan het beheer van instellingen deze waarden later uit de database overschrijven.
*/
return [

    /*
    | Ongebruikt tegoed aan het einde van de maand: OPEN PUNT (CLAUDE.md 13.1).
    | VOORSTEL: 'meenemen' (het saldo blijft staan). Andere waarde: 'vervallen' (het positieve saldo vervalt op de 1e).
    */
    'unused_credit' => env('MEBRANDING_ONGEBRUIKT_TEGOED', 'meenemen'),
    'unused_credit_is_proposal' => true,

    /* Facturen: btw, betaaltermijn (1e van de maand, 14 dagen) en herinneringen na 7 en 14 dagen. */
    'vat_rate' => 21,
    'payment_days' => 14,
    'reminder_days' => [7, 14],
    'invoice_prefix' => 'MB',

    /*
    | Gegevens van MEBRANDING op de factuur. Er is nog geen KvK-inschrijving (CLAUDE.md 13.12): placeholders.
    */
    'sender' => [
        'name' => 'MEBRANDING',
        'address' => env('MEBRANDING_ADRES', '[adres]'),
        'kvk' => env('MEBRANDING_KVK', '[KvK-nummer]'),
        'vat' => env('MEBRANDING_BTW', '[btw-nummer]'),
        'iban' => env('MEBRANDING_IBAN', '[IBAN]'),
        'email' => 'facturen@mebranding.nl',
    ],

    /* Cijfers die een creator of het team handmatig kan invoeren (CLAUDE.md 8: elk proces ook handmatig). */
    'metrics' => [
        'views' => 'Views',
        'reach' => 'Bereik',
        'likes' => 'Likes',
        'comments' => 'Reacties',
        'shares' => 'Shares',
        'saves' => 'Opgeslagen',
    ],

    /* Maximale grootte van een screenshot met cijfers, in kB. */
    'screenshot_max_kb' => 10240,

];
