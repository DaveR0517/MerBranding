<?php

use App\Http\Controllers\Team\BeheerController;
use App\Http\Controllers\Team\TeamController;
use Illuminate\Support\Facades\Route;

/* Omgeving team: /team */

Route::get('/', [TeamController::class, 'werkvoorraad'])->name('werkvoorraad');
Route::get('samenwerkingen', [TeamController::class, 'samenwerkingen'])->name('samenwerkingen');
Route::get('samenwerkingen/{samenwerking}', [TeamController::class, 'samenwerking'])->name('samenwerking');
Route::post('samenwerkingen/{samenwerking}/uitnodigen', [TeamController::class, 'uitnodigen'])->name('uitnodigen');
Route::post('samenwerkingen/{samenwerking}/feedback', [TeamController::class, 'feedback'])->name('feedback');
Route::post('samenwerkingen/{samenwerking}/goedkeuren', [TeamController::class, 'goedkeuren'])->name('goedkeuren');
Route::post('samenwerkingen/{samenwerking}/annuleren', [TeamController::class, 'annuleren'])->name('annuleren');
Route::post('samenwerkingen/{samenwerking}/niet-verschenen', [TeamController::class, 'nietVerschenen'])->name('niet-verschenen');
Route::get('bedrijven', [BeheerController::class, 'bedrijven'])->name('bedrijven');
Route::post('bedrijven', [BeheerController::class, 'bedrijfOpslaan'])->name('bedrijven.opslaan');
Route::get('creators', [BeheerController::class, 'creators'])->name('creators');
Route::post('creators', [BeheerController::class, 'creatorOpslaan'])->name('creators.opslaan');
Route::get('logboek', [TeamController::class, 'logboek'])->name('logboek');
