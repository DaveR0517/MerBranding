<?php

use App\Http\Controllers\Creator\ScoreController;
use Illuminate\Support\Facades\Route;

/* Module score-en-klassement, omgeving creator: /creator/score */

Route::get('score', [ScoreController::class, 'index'])->name('score');
