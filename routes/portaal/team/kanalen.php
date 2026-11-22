<?php

use App\Http\Controllers\Team\Kanalen\BerichtenController;
use App\Http\Controllers\Team\Kanalen\KanalenController;
use App\Http\Controllers\Team\Kanalen\PlaatsenController;
use App\Http\Controllers\Team\Kanalen\PostsController;
use Illuminate\Support\Facades\Route;

/* Eigen kanalen, omgeving team: /team/kanalen. Het Instagram- en TikTok-account van MEBRANDING zelf; recht kanalen.beheren. */

Route::prefix('kanalen')->name('kanalen.')->middleware('can:kanalen.beheren')->group(function () {
    Route::get('/', [KanalenController::class, 'index'])->name('index');

    Route::get('posts', [PostsController::class, 'index'])->name('posts');
    Route::post('posts/{media}/reacties', [PostsController::class, 'reactiesVerversen'])->name('reacties.verversen');
    Route::post('reacties/{reactie}/antwoord', [PostsController::class, 'beantwoorden'])->name('reacties.antwoord');

    Route::get('berichten', [BerichtenController::class, 'index'])->name('berichten');
    Route::post('berichten/{gesprek}', [BerichtenController::class, 'sturen'])->name('berichten.sturen');

    Route::post('plaatsen/{ontwerp}', PlaatsenController::class)->name('plaatsen');

    // Deze adressen staan bij Meta en TikTok als redirect-URI: /team/kanalen/instagram/terug en /team/kanalen/tiktok/terug.
    Route::controller(KanalenController::class)->prefix('{platform}')->whereIn('platform', ['instagram', 'tiktok'])->group(function () {
        Route::post('koppelen', 'koppelen')->name('koppelen');
        Route::get('terug', 'terug')->name('terug');
        Route::post('ontkoppelen', 'ontkoppelen')->name('ontkoppelen');
        Route::post('verversen', 'verversen')->name('verversen');
    });
});
