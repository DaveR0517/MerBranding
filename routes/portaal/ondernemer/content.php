<?php

use App\Http\Controllers\Ondernemer\ContentController;
use Illuminate\Support\Facades\Route;

/* Module content en koppelingen, omgeving ondernemer: /ondernemer/content */

Route::prefix('content')->name('content.')->controller(ContentController::class)->group(function () {
    Route::get('kalender', 'kalender')->name('kalender');
    Route::post('notities', 'notitieOpslaan')->name('notities.opslaan');
    Route::delete('notities/{notitie}', 'notitieVerwijderen')->name('notities.verwijderen');
    Route::get('ideeen', 'ideeen')->name('ideeen');
    Route::post('ideeen', 'ideeOpslaan')->name('ideeen.opslaan');
    Route::delete('ideeen/{idee}', 'ideeVerwijderen')->name('ideeen.verwijderen');
    Route::get('rechten', 'rechten')->name('rechten');
    Route::post('rechten/{recht}/verlengen', 'verlengen')->name('rechten.verlengen');
    Route::get('koppelingen', 'koppelingen')->name('koppelingen');
    Route::post('koppelingen/{platform}/toestemming', 'toestemming')->name('koppelingen.toestemming');
    Route::post('koppelingen/{platform}/handmatig', 'handmatig')->name('koppelingen.handmatig');
    Route::post('koppelingen/{platform}/verbreken', 'verbreken')->name('koppelingen.verbreken');
    Route::get('koppelingen/{platform}/terug', 'terug')->name('koppelingen.terug');
    Route::get('zichtbaarheid', 'zichtbaarheid')->name('zichtbaarheid');
    Route::post('zichtbaarheid/interesse', 'interesse')->name('zichtbaarheid.interesse');
});
