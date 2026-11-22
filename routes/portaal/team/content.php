<?php

use App\Http\Controllers\Team\ContentController;
use Illuminate\Support\Facades\Route;

/* Module content en koppelingen, omgeving team: /team/content */

Route::prefix('content')->name('content.')->controller(ContentController::class)->group(function () {
    Route::get('koppelingen', 'koppelingen')->name('koppelingen');
    Route::post('publicaties/{publicatie}/controle', 'postControle')->name('publicaties.controle');
    Route::get('planning', 'planning')->name('planning');
    Route::get('bedrijven/{bedrijf?}', 'bedrijf')->name('bedrijf');
    Route::post('bedrijven/{bedrijf}/notities', 'notitieOpslaan')->name('notities.opslaan');
    Route::delete('notities/{notitie}', 'notitieVerwijderen')->name('notities.verwijderen');
    Route::post('bedrijven/{bedrijf}/ideeen', 'ideeOpslaan')->name('ideeen.opslaan');
    Route::patch('ideeen/{idee}', 'ideeBijwerken')->name('ideeen.bijwerken');
    Route::post('bedrijven/{bedrijf}/rechten', 'rechtOpslaan')->name('rechten.opslaan');
    Route::patch('rechten/{recht}', 'rechtBijwerken')->name('rechten.bijwerken');
    Route::get('radar', 'radar')->name('radar');
});
