<?php

use App\Http\Controllers\Portaal\PortaalController;
use App\Http\Middleware\AlleenOmgeving;
use Illuminate\Support\Facades\Route;

/*
| Eén applicatie, twee hostnamen (www stuurt Forge/nginx door naar de kale domeinnaam):
| - mebranding.nl      de website (Blade), routes/site.php
| - app.mebranding.nl  alle omgevingen (Inertia), hieronder; inloggen via Fortify
| Op het Forge-testadres delen ze één host; het portaal staat dan onder APP_PREFIX (zie config/mebranding.php).
|
| Elke omgeving heeft eigen routes (routes/portaal), controllers (app/Http/Controllers/<Omgeving>)
| en schermen (resources/js/pages/<omgeving>). De kern (statussen, tegoed, logboek) is gedeeld: app/Services.
*/

Route::domain(config('mebranding.domains.site'))->group(base_path('routes/site.php'));

Route::domain(config('mebranding.domains.app'))->prefix(config('mebranding.app_prefix'))->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', [PortaalController::class, 'start'])->name('start');
        Route::redirect('dashboard', '/'.trim(config('mebranding.app_prefix').'/', '/'))->name('dashboard');

        Route::get('meldingen', [PortaalController::class, 'meldingen'])->name('meldingen');
        Route::post('meldingen/alles-gelezen', [PortaalController::class, 'allesGelezen'])->name('meldingen.alles');
        Route::post('meldingen/{id}', [PortaalController::class, 'gelezen'])->name('meldingen.gelezen');
        Route::get('video/{versie}', [PortaalController::class, 'video'])->name('video');
        Route::post('inloggen-als/{user}', [PortaalController::class, 'inloggenAls'])->name('inloggen-als');
        Route::post('terug', [PortaalController::class, 'terug'])->name('terug');

        Route::prefix('ondernemer')->name('ondernemer.')->middleware(AlleenOmgeving::class.':ondernemer')->group(base_path('routes/portaal/ondernemer.php'));
        Route::prefix('creator')->name('creator.')->middleware(AlleenOmgeving::class.':creator')->group(base_path('routes/portaal/creator.php'));
        Route::prefix('team')->name('team.')->middleware(AlleenOmgeving::class.':team')->group(base_path('routes/portaal/team.php'));
    });

    require __DIR__.'/settings.php';
});
