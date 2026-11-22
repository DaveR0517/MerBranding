<?php

namespace App\Providers;

use App\Services\Instellingen;
use Illuminate\Support\ServiceProvider;

/** Laat de instellingen uit beheer (tabel settings) config/mebranding.php overschrijven, bij elk opstarten. */
class InstellingenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Instellingen::class);
    }

    public function boot(): void
    {
        $this->app->make(Instellingen::class)->laad();
    }
}
