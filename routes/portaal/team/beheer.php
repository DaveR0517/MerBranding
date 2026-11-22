<?php

use App\Http\Controllers\Team\Beheer\BerichtsjablonenController;
use App\Http\Controllers\Team\Beheer\InstellingenController;
use App\Http\Controllers\Team\Beheer\MedewerkersController;
use App\Http\Controllers\Team\Beheer\PakkettenController;
use Illuminate\Support\Facades\Route;

/* Module team-en-instellingen (routenamen team.beheer.*): /team/medewerkers, /team/instellingen, /team/pakketten, /team/berichtsjablonen */

Route::name('beheer.')->group(function () {
    Route::get('mijn-rechten', [MedewerkersController::class, 'mijnRechten'])->name('mijn-rechten');

    Route::get('medewerkers', [MedewerkersController::class, 'index'])->name('medewerkers');
    Route::post('medewerkers', [MedewerkersController::class, 'uitnodigen'])->name('medewerkers.uitnodigen');
    Route::put('medewerkers/{user}/rollen', [MedewerkersController::class, 'rollen'])->name('medewerkers.rollen');
    Route::post('medewerkers/{user}/deactiveren', [MedewerkersController::class, 'deactiveren'])->name('medewerkers.deactiveren');

    Route::get('instellingen', [InstellingenController::class, 'index'])->name('instellingen');
    Route::put('instellingen', [InstellingenController::class, 'opslaan'])->name('instellingen.opslaan');

    Route::get('pakketten', [PakkettenController::class, 'index'])->name('pakketten');
    Route::post('pakketten', [PakkettenController::class, 'pakket'])->name('pakketten.toevoegen');
    Route::post('pakketten/{plan}/versies', [PakkettenController::class, 'versie'])->name('pakketten.versie');
    Route::delete('pakketten/versies/{versie}', [PakkettenController::class, 'intrekken'])->name('pakketten.intrekken');

    Route::get('berichtsjablonen', [BerichtsjablonenController::class, 'index'])->name('berichtsjablonen');
    Route::put('berichtsjablonen/{sjabloon}', [BerichtsjablonenController::class, 'opslaan'])->name('berichtsjablonen.opslaan');
    Route::post('berichtsjablonen/{sjabloon}/testmail', [BerichtsjablonenController::class, 'testmail'])->name('berichtsjablonen.testmail');
});
