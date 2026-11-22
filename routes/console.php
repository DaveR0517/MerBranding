<?php

use App\Jobs\MeldGebruiksrechten;
use App\Services\Samenwerking;
use Illuminate\Support\Facades\Schedule;

/* Timers van de keten (CLAUDE.md 7.4). Forge draait schedule:run elke minuut. */

// Na de meetperiode van 14 dagen: Live wordt Afgerond en de ondernemer wordt gevraagd te beoordelen.
Schedule::call(fn () => app(Samenwerking::class)->rondAf())->name('samenwerkingen-afronden')->hourly()->withoutOverlapping();

// Verlopen uitnodigingen sluiten, zodat het team opnieuw kan koppelen.
Schedule::call(fn () => app(Samenwerking::class)->verlopenUitnodigingen())->name('uitnodigingen-verlopen')->everyFifteenMinutes()->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Gebruiksrechten (module content-koppelingen-planning): melding 30 en 7 dagen voor het einde (o-rechten). Tijd: VOORSTEL.
Schedule::job(new MeldGebruiksrechten)->name('gebruiksrechten-melden')->dailyAt('08:47')->withoutOverlapping();
