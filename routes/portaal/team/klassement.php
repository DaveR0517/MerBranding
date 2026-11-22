<?php

use App\Http\Controllers\Team\KlassementController;
use Illuminate\Support\Facades\Route;

/* Module score-en-klassement, omgeving team: /team/klassement */

Route::get('klassement', [KlassementController::class, 'index'])->name('klassement');
Route::post('klassement/voorstellen/{voorstel}', [KlassementController::class, 'besluit'])->name('klassement.besluit');
Route::post('klassement/creators/{creator}/niveau', [KlassementController::class, 'niveau'])->name('klassement.niveau');
Route::post('klassement/creators/{creator}/status', [KlassementController::class, 'status'])->name('klassement.status');
