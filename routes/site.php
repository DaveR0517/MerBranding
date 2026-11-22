<?php

use App\Http\Controllers\Site\LeadController;
use Illuminate\Support\Facades\Route;

/* Website mebranding.nl (Blade). Paginas staan in resources/views/site. */

Route::view('/', 'site.home')->name('site.home');
Route::view('pakketten', 'site.pakketten')->name('site.pakketten');
Route::view('voor-ondernemers', 'site.ondernemers')->name('site.ondernemers');
Route::view('voor-creators', 'site.creators')->name('site.creators');
Route::view('privacy', 'site.privacy')->name('site.privacy');
Route::view('cookies', 'site.cookies')->name('site.cookies');

Route::middleware('throttle:leads')->group(function () {
    Route::post('voor-ondernemers', [LeadController::class, 'ondernemer'])->name('site.ondernemers.aanmelden');
    Route::post('voor-creators', [LeadController::class, 'creator'])->name('site.creators.aanmelden');
});
