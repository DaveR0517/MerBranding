<?php

use App\Http\Controllers\Team\Studio\ExportController;
use App\Http\Controllers\Team\Studio\FeedplannerController;
use App\Http\Controllers\Team\Studio\MediatheekController;
use App\Http\Controllers\Team\Studio\OntwerpenController;
use App\Http\Controllers\Team\Studio\SjablonenController;
use Illuminate\Support\Facades\Route;

/* MeStudio, omgeving team: /team/studio. Eigen marketing van MEBRANDING; recht studio.gebruiken. */

Route::prefix('studio')->name('studio.')->middleware('can:studio.gebruiken')->group(function () {
    Route::get('/', fn () => redirect()->route('team.studio.ontwerpen.index'))->name('start');

    Route::controller(OntwerpenController::class)->prefix('ontwerpen')->name('ontwerpen.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{ontwerp}', 'bewerken')->name('bewerken');
        Route::put('{ontwerp}', 'opslaan')->name('opslaan');
        Route::delete('{ontwerp}', 'verwijderen')->name('verwijderen');
        Route::post('{ontwerp}/dupliceren', 'dupliceren')->name('dupliceren');
        Route::post('{ontwerp}/sjabloon', 'alsSjabloon')->name('sjabloon');
        Route::get('{ontwerp}/voorvertoning', 'voorvertoning')->name('voorvertoning');
    });

    Route::controller(ExportController::class)->group(function () {
        Route::post('ontwerpen/{ontwerp}/exports', 'store')->name('exports.store');
        Route::get('ontwerpen/{ontwerp}/exports', 'lijst')->name('exports.lijst');
        Route::get('ontwerpen/{ontwerp}/zip', 'zip')->name('exports.zip');
        Route::get('exports/{export}', 'download')->name('exports.download');
    });

    Route::controller(SjablonenController::class)->prefix('sjablonen')->name('sjablonen.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('achtergrond', 'vanAchtergrond')->name('achtergrond');
        Route::get('{sjabloon}', 'bewerken')->name('bewerken');
        Route::put('{sjabloon}', 'opslaan')->name('opslaan');
        Route::post('{sjabloon}/gebruiken', 'gebruiken')->name('gebruiken');
        Route::post('{sjabloon}/dupliceren', 'dupliceren')->name('dupliceren');
        Route::patch('{sjabloon}/naam', 'hernoemen')->name('hernoemen');
        Route::post('{sjabloon}/archiveren', 'archiveren')->name('archiveren');
    });

    Route::controller(FeedplannerController::class)->prefix('feedplanner')->name('feedplanner.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('volgorde', 'volgorde')->name('volgorde');
        Route::patch('{ontwerp}', 'bijwerken')->name('bijwerken');
    });

    Route::controller(MediatheekController::class)->prefix('mediatheek')->name('mediatheek.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{media}/bestand', 'bestand')->name('bestand');
        Route::delete('{media}', 'verwijderen')->name('verwijderen');
    });
});
