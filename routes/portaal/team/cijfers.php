<?php

use App\Http\Controllers\Team\CijfersController;
use App\Http\Controllers\Team\FinancieelController;
use Illuminate\Support\Facades\Route;

/* Module cijfers en geld, omgeving team: dashboard, cijfers, maandrapporten en financieel. */

Route::get('dashboard', [CijfersController::class, 'dashboard'])->name('dashboard');

Route::get('cijfers', [CijfersController::class, 'index'])->name('cijfers');
Route::post('cijfers/publicaties/{publicatie}', [CijfersController::class, 'invoeren'])->name('cijfers.invoeren');
Route::post('cijfers/metingen/{snapshot}/corrigeren', [CijfersController::class, 'corrigeren'])->name('cijfers.corrigeren');
Route::post('cijfers/samenwerkingen/{samenwerking}/akkoord', [CijfersController::class, 'akkoord'])->name('cijfers.akkoord');
Route::get('cijfers/metingen/{snapshot}/screenshot', [CijfersController::class, 'screenshot'])->name('cijfers.screenshot');

Route::get('maandrapport/{bedrijf}/{maand?}', [CijfersController::class, 'maandrapport'])->name('maandrapport')->where('maand', '\d{4}-\d{2}');
Route::get('maandrapport/{bedrijf}/{maand}/afdrukken', [CijfersController::class, 'maandrapportAfdrukken'])->name('maandrapport.afdrukken')->where('maand', '\d{4}-\d{2}');

Route::get('financieel', [FinancieelController::class, 'index'])->name('financieel');
Route::post('financieel/betaalruns', [FinancieelController::class, 'samenstellen'])->name('financieel.samenstellen');
Route::get('financieel/betaalruns/{run}/export', [FinancieelController::class, 'export'])->name('financieel.export');
Route::post('financieel/betaalruns/{run}/uitbetaald', [FinancieelController::class, 'uitbetaald'])->name('financieel.uitbetaald');
Route::post('financieel/vergoedingen/{payout}/uit-run', [FinancieelController::class, 'uitRun'])->name('financieel.uit-run');
Route::post('financieel/maandfacturen', [FinancieelController::class, 'maandfacturen'])->name('financieel.maandfacturen');
Route::get('financieel/facturen/{factuur}', [FinancieelController::class, 'factuur'])->name('financieel.factuur');
Route::post('financieel/facturen/{factuur}/betaald', [FinancieelController::class, 'betaald'])->name('financieel.betaald');
