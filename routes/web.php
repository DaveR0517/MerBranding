<?php

use Illuminate\Support\Facades\Route;

/*
| Eén applicatie, twee hostnamen (www stuurt Forge/nginx door naar de kale domeinnaam):
| - mebranding.nl      de website (Blade), routes/site.php
| - app.mebranding.nl  alle portalen (Inertia), hieronder; inloggen via Fortify
| Op het Forge-testadres delen ze één host; het portaal staat dan onder APP_PREFIX (zie config/mebranding.php).
*/

Route::domain(config('mebranding.domains.site'))->group(base_path('routes/site.php'));

Route::domain(config('mebranding.domains.app'))->prefix(config('mebranding.app_prefix'))->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::inertia('dashboard', 'dashboard')->name('dashboard');
    });

    require __DIR__.'/settings.php';
});
