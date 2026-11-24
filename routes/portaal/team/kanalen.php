<?php

use App\Http\Controllers\Team\Kanalen\BerichtenController;
use App\Http\Controllers\Team\Kanalen\KanalenController;
use App\Http\Controllers\Team\Kanalen\MailController;
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

/* Mail: de Gmail-postvakken van MEBRANDING (Google Workspace). Eigen toegangsregels per postvak (App\Services\Gmail\Postvakken), niet kanalen.beheren. */
Route::prefix('kanalen/mail')->name('kanalen.mail')->group(function () {
    Route::get('/', [MailController::class, 'index']);
    Route::post('{postvak}/akkoord', [MailController::class, 'akkoord'])->name('.akkoord');
    Route::post('{postvak}/{gesprek}/antwoord', [MailController::class, 'beantwoorden'])->middleware('throttle:30,1')->name('.antwoord');
    Route::post('{postvak}/{gesprek}/archiveren', [MailController::class, 'archiveren'])->name('.archiveren');
    Route::get('{postvak}/bericht/{bericht}/bijlage/{bijlage}', [MailController::class, 'bijlage'])->name('.bijlage');
});
