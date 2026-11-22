<?php

use App\Services\Samenwerking;
use Illuminate\Support\Facades\Schedule;

/* Timers van de keten (CLAUDE.md 7.4). Forge draait schedule:run elke minuut. */

// Na de meetperiode van 14 dagen: Live wordt Afgerond en de ondernemer wordt gevraagd te beoordelen.
Schedule::call(fn () => app(Samenwerking::class)->rondAf())->name('samenwerkingen-afronden')->hourly()->withoutOverlapping();

// Verlopen uitnodigingen sluiten, zodat het team opnieuw kan koppelen.
Schedule::call(fn () => app(Samenwerking::class)->verlopenUitnodigingen())->name('uitnodigingen-verlopen')->everyFifteenMinutes()->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes();
