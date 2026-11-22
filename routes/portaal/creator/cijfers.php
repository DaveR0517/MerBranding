<?php

use App\Http\Controllers\Creator\CijfersController;
use Illuminate\Support\Facades\Route;

/* Module cijfers en geld, omgeving creator: handmatige cijfers met screenshot tijdens de meetperiode. */

Route::get('cijfers', [CijfersController::class, 'index'])->name('cijfers');
Route::get('cijfers/{samenwerking}', [CijfersController::class, 'toon'])->name('cijfers.invoeren');
Route::post('cijfers/{samenwerking}', [CijfersController::class, 'opslaan'])->name('cijfers.opslaan');
Route::get('cijfers/metingen/{snapshot}/screenshot', [CijfersController::class, 'screenshot'])->name('cijfers.screenshot');
