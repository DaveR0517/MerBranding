<?php

use App\Http\Controllers\Ondernemer\OndernemerController;
use Illuminate\Support\Facades\Route;

/* Omgeving ondernemer: /ondernemer */

Route::get('/', [OndernemerController::class, 'overzicht'])->name('overzicht');
Route::get('aanvragen', [OndernemerController::class, 'aanvragen'])->name('aanvragen');
Route::post('samenwerkingen', [OndernemerController::class, 'opslaan'])->name('opslaan');
Route::get('samenwerkingen/{samenwerking}', [OndernemerController::class, 'samenwerking'])->name('samenwerking');
Route::post('samenwerkingen/{samenwerking}/opmerking', [OndernemerController::class, 'opmerking'])->name('opmerking');
Route::post('samenwerkingen/{samenwerking}/annuleren', [OndernemerController::class, 'annuleren'])->name('annuleren');
Route::post('samenwerkingen/{samenwerking}/beoordelen', [OndernemerController::class, 'beoordelen'])->name('beoordelen');
