<?php

use App\Http\Controllers\Creator\CreatorController;
use Illuminate\Support\Facades\Route;

/* Omgeving creator: /creator */

Route::get('/', [CreatorController::class, 'overzicht'])->name('overzicht');
Route::get('klussen/{samenwerking}', [CreatorController::class, 'klus'])->name('klus');
Route::post('uitnodigingen/{uitnodiging}/accepteren', [CreatorController::class, 'accepteren'])->name('accepteren');
Route::post('uitnodigingen/{uitnodiging}/weigeren', [CreatorController::class, 'weigeren'])->name('weigeren');
Route::post('klussen/{samenwerking}/tijdvak', [CreatorController::class, 'tijdvak'])->name('tijdvak');
Route::post('klussen/{samenwerking}/inchecken', [CreatorController::class, 'inchecken'])->name('inchecken');
Route::post('klussen/{samenwerking}/klaar', [CreatorController::class, 'klaar'])->name('klaar');
Route::post('klussen/{samenwerking}/indienen', [CreatorController::class, 'indienen'])->name('indienen');
Route::post('klussen/{samenwerking}/live', [CreatorController::class, 'live'])->name('live');
