<?php

use App\Http\Controllers\Site\LeadController;
use Illuminate\Support\Facades\Route;

/* Website mebranding.nl (Blade, richting F). Paginas staan in resources/views/site. */

Route::view('/', 'site.home')->name('site.home');
Route::view('hoe-het-werkt', 'site.hoe-het-werkt')->name('site.hoe-het-werkt');
Route::view('pakketten', 'site.pakketten')->name('site.pakketten');
Route::view('diamond', 'site.diamond')->name('site.diamond');
Route::view('creators', 'site.creators')->name('site.creators');
Route::view('academy', 'site.academy')->name('site.academy');
Route::view('kennismaken', 'site.kennismaken')->name('site.kennismaken');
Route::view('aanmelden-creator', 'site.aanmelden-creator')->name('site.aanmelden-creator');
Route::view('privacy', 'site.privacy')->name('site.privacy');
Route::view('cookies', 'site.cookies')->name('site.cookies');

// Inloggen gebeurt in het portaal.
Route::get('inloggen', fn () => redirect()->route('login'))->name('site.inloggen');

// Oude adressen van de vorige website.
Route::permanentRedirect('voor-ondernemers', 'kennismaken');
Route::permanentRedirect('voor-creators', 'aanmelden-creator');

Route::middleware('throttle:leads')->group(function () {
    Route::post('kennismaken', [LeadController::class, 'ondernemer'])->name('site.kennismaken.versturen');
    Route::post('aanmelden-creator', [LeadController::class, 'creator'])->name('site.aanmelden-creator.versturen');
});
