<?php

use App\Http\Controllers\Portaal\WebappController;
use Illuminate\Support\Facades\Route;

/* Webapp: manifest en service worker zonder inloggen (de browser haalt ze los op), pushabonnementen met inloggen. */
Route::get('manifest.webmanifest', [WebappController::class, 'manifest'])->name('webapp.manifest');
Route::get('sw.js', [WebappController::class, 'serviceWorker'])->name('webapp.sw');

Route::middleware('auth')->prefix('push')->name('push.')->group(function () {
    Route::get('sleutel', [WebappController::class, 'sleutel'])->name('sleutel');
    Route::post('abonneren', [WebappController::class, 'abonneren'])->name('abonneren');
    Route::post('opzeggen', [WebappController::class, 'opzeggen'])->name('opzeggen');
    Route::post('proef', [WebappController::class, 'proef'])->middleware('throttle:5,1')->name('proef');
});
