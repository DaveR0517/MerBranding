<?php

use App\Http\Controllers\Creator\ContentController;
use Illuminate\Support\Facades\Route;

/* Module content en koppelingen, omgeving creator: /creator/content */

Route::prefix('content')->name('content.')->controller(ContentController::class)->group(function () {
    Route::get('koppelingen', 'koppelingen')->name('koppelingen');
    Route::post('koppelingen/{platform}/toestemming', 'toestemming')->name('koppelingen.toestemming');
    Route::post('koppelingen/{platform}/handmatig', 'handmatig')->name('koppelingen.handmatig');
    Route::post('koppelingen/{platform}/verbreken', 'verbreken')->name('koppelingen.verbreken');
    Route::get('koppelingen/{platform}/terug', 'terug')->name('koppelingen.terug');
});
