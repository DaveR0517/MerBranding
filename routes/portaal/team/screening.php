<?php

use App\Http\Controllers\Team\ScreeningController;
use Illuminate\Support\Facades\Route;

/* Module creator-profiel, omgeving team: screening van aanmeldingen als creator (/team/screening). */

Route::get('screening', [ScreeningController::class, 'index'])->name('screening.index');
Route::get('screening/{lead}', [ScreeningController::class, 'toon'])->name('screening.toon');
Route::post('screening/{lead}/goedkeuren', [ScreeningController::class, 'goedkeuren'])->name('screening.goedkeuren');
Route::post('screening/{lead}/aanvulling', [ScreeningController::class, 'aanvulling'])->name('screening.aanvulling');
Route::post('screening/{lead}/uitnodigingslink', [ScreeningController::class, 'uitnodigingslink'])->name('screening.uitnodigingslink');
Route::post('screening/{lead}/afwijzen', [ScreeningController::class, 'afwijzen'])->name('screening.afwijzen');
