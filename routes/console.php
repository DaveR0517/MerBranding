<?php

use App\Jobs\MeldGebruiksrechten;
use App\Jobs\VerversEigenKanalen;
use App\Services\Beschikbaarheid;
use App\Services\Geld;
use App\Services\Samenwerking;
use App\Services\Score;
use Illuminate\Support\Facades\Schedule;

/* Timers van de keten (CLAUDE.md 7.4). Forge draait schedule:run elke minuut. */

// Na de meetperiode van 14 dagen: Live wordt Afgerond en de ondernemer wordt gevraagd te beoordelen.
Schedule::call(fn () => app(Samenwerking::class)->rondAf())->name('samenwerkingen-afronden')->hourly()->withoutOverlapping();

// Verlopen uitnodigingen sluiten, zodat het team opnieuw kan koppelen.
Schedule::call(fn () => app(Samenwerking::class)->verlopenUitnodigingen())->name('uitnodigingen-verlopen')->everyFifteenMinutes()->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Ik ben beschikbaar: verlopen beschikbaarheid en de gedeelde plek wissen.
Schedule::call(fn () => app(Beschikbaarheid::class)->opruimen())->name('beschikbaarheid-opruimen')->everyFifteenMinutes()->withoutOverlapping();

// Score en niveau (module score-en-klassement, docs/02 flow 5). Tijden en ritme: VOORSTEL.
Schedule::call(fn () => app(Score::class)->herberekenAlles())->name('score-herberekenen')->dailyAt('03:17')->withoutOverlapping();
Schedule::call(fn () => app(Score::class)->niveaumeting())->name('score-niveaumeting')->monthlyOn(1, '04:17')->withoutOverlapping();
Schedule::call(fn () => app(Score::class)->gevaarMeldingen())->name('score-niveau-in-gevaar')->dailyAt('10:13')->withoutOverlapping();

// Gebruiksrechten (module content-koppelingen-planning): melding 30 en 7 dagen voor het einde (o-rechten). Tijd: VOORSTEL.
Schedule::job(new MeldGebruiksrechten)->name('gebruiksrechten-melden')->dailyAt('08:47')->withoutOverlapping();

/* Module cijfers en geld (CLAUDE.md 7.4): tegoed en factuur op de 1e, herinneringen 7 en 14 dagen na de vervaldatum, betaalrun op vrijdag. */
Schedule::call(fn () => app(Geld::class)->maandelijks())->name('maandelijks-tegoed-en-factuur')->monthlyOn(1, '06:10')->timezone('Europe/Amsterdam')->withoutOverlapping();
Schedule::call(fn () => app(Geld::class)->herinneringen())->name('factuurherinneringen')->dailyAt('09:05')->timezone('Europe/Amsterdam')->withoutOverlapping();
Schedule::call(fn () => app(Geld::class)->samenstellen(Geld::vrijdag()))->name('betaalrun-samenstellen')->fridays()->at('07:45')->timezone('Europe/Amsterdam')->withoutOverlapping();

// Eigen kanalen (Instagram en TikTok van MEBRANDING): elk uur profiel, posts, cijfers en berichten ophalen, tokens op tijd verlengen.
Schedule::job(new VerversEigenKanalen)->name('eigen-kanalen-verversen')->hourlyAt(23)->withoutOverlapping();
