<?php

use App\Http\Controllers\Creator\CreatorProfielController;
use Illuminate\Support\Facades\Route;

/* Module creator-profiel, omgeving creator: start (checklist en raamovereenkomst), profiel, account, vergoedingen en agenda. */

Route::get('start', [CreatorProfielController::class, 'start'])->name('profiel.start');
Route::post('raamovereenkomst', [CreatorProfielController::class, 'tekenen'])->name('profiel.tekenen');
Route::get('profiel', [CreatorProfielController::class, 'profiel'])->name('profiel.index');
Route::post('profiel', [CreatorProfielController::class, 'profielOpslaan'])->name('profiel.opslaan');
Route::get('account', [CreatorProfielController::class, 'account'])->name('profiel.account');
Route::post('account', [CreatorProfielController::class, 'accountOpslaan'])->name('profiel.uitbetaling');
Route::get('vergoedingen', [CreatorProfielController::class, 'vergoedingen'])->name('profiel.vergoedingen');
Route::get('agenda', [CreatorProfielController::class, 'agenda'])->name('profiel.agenda');
Route::get('agenda/{samenwerking}/{soort}.ics', [CreatorProfielController::class, 'ics'])->whereIn('soort', ['bezoek', 'deadline', 'publicatie'])->name('profiel.ics');
