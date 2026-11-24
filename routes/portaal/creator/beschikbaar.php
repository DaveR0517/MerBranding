<?php

use App\Http\Controllers\Creator\BeschikbaarController;
use Illuminate\Support\Facades\Route;

/* Ik ben beschikbaar: een paar uur een klus in de buurt kunnen doen (docs/plan-kaart-en-snelle-klussen.md). Werkt alleen met config mebranding.creator_kaart. */
Route::post('beschikbaar', [BeschikbaarController::class, 'aan'])->name('beschikbaar.aan');
Route::post('beschikbaar/stop', [BeschikbaarController::class, 'uit'])->name('beschikbaar.uit');
