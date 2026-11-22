<?php

use App\Http\Controllers\Team\Kanalen\WebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
| Publieke adressen op het app-domein voor Meta en TikTok: zonder inloggen en zonder CSRF (module eigen kanalen).
| Laden in routes/web.php binnen de groep van het app-domein (met APP_PREFIX), buiten de auth-middleware:
|     require base_path('routes/portaal-webhooks.php');
| Op het testadres: https://mebranding.on-forge.com/app/webhooks/instagram en .../app/webhooks/tiktok.
*/

Route::controller(WebhookController::class)->withoutMiddleware(ValidateCsrfToken::class)->group(function () {
    Route::get('webhooks/instagram', 'instagramVerifieer')->name('webhooks.instagram.verifieer');
    Route::post('webhooks/instagram', 'instagram')->name('webhooks.instagram');
    Route::post('webhooks/instagram/ontkoppeld', 'instagramOntkoppeld')->name('webhooks.instagram.ontkoppeld');
    Route::post('webhooks/instagram/verwijderen', 'instagramVerwijderen')->name('webhooks.instagram.verwijderen');
    Route::get('webhooks/instagram/verwijderd/{code}', 'verwijderd')->name('webhooks.instagram.verwijderd');
    Route::post('webhooks/tiktok', 'tiktok')->name('webhooks.tiktok');

    // Tijdelijke ondertekende link (1 uur) naar een exportbestand, zodat Meta het kan ophalen bij plaatsen.
    Route::get('kanalen/bestand/{export}', 'bestand')->middleware('signed')->name('kanalen.bestand');
});
