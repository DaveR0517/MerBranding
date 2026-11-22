<?php

use App\Http\Controllers\Ondernemer\ResultatenController;
use Illuminate\Support\Facades\Route;

/* Module cijfers en geld, omgeving ondernemer: resultaten, mediabibliotheek, maandrapport en facturen. */

Route::get('resultaten', [ResultatenController::class, 'index'])->name('resultaten');
Route::get('mediabibliotheek', [ResultatenController::class, 'media'])->name('mediabibliotheek');
Route::get('maandrapport/{maand?}', [ResultatenController::class, 'maandrapport'])->name('maandrapport')->where('maand', '\d{4}-\d{2}');
Route::get('maandrapport/{maand}/afdrukken', [ResultatenController::class, 'afdrukken'])->name('maandrapport.afdrukken')->where('maand', '\d{4}-\d{2}');
Route::get('facturen', [ResultatenController::class, 'facturen'])->name('facturen');
Route::get('facturen/{factuur}', [ResultatenController::class, 'factuur'])->name('facturen.toon');
