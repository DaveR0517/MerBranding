<?php

use App\Jobs\MeldGebruiksrechten;
use App\Jobs\VerversEigenKanalen;
use App\Services\Bewaking;
use App\Services\Dagoverzicht;
use App\Services\Geld;
use App\Services\Herinneringen;
use App\Services\Samenwerking;
use App\Services\Score;
use Illuminate\Support\Facades\Schedule;

/* Timers van de keten (CLAUDE.md 7.4). Forge draait schedule:run elke minuut. */

// Na de meetperiode van 14 dagen: Live wordt Afgerond en de ondernemer wordt gevraagd te beoordelen.
Schedule::call(fn () => app(Samenwerking::class)->rondAf())->name('samenwerkingen-afronden')->hourly()->withoutOverlapping();

// Verlopen uitnodigingen sluiten, zodat het team opnieuw kan koppelen.
Schedule::call(fn () => app(Samenwerking::class)->verlopenUitnodigingen())->name('uitnodigingen-verlopen')->everyFifteenMinutes()->withoutOverlapping();

// Herinneringen en teamsignalen (App\Services\Herinneringen), elk maar één keer per samenwerking.
// Ochtendronde: bezoek morgen, publicatiedag en post vergeten (sjablonen noemen 09:00). Overdag elk kwartier de rest, 's nachts geen meldingen.
Schedule::call(function () {
    $h = app(Herinneringen::class);
    $h->bezoekMorgen();
    $h->publiceerVandaag();
    $h->post();
})->name('herinneringen-ochtend')->dailyAt('09:03')->timezone('Europe/Amsterdam')->withoutOverlapping();
Schedule::call(function () {
    $h = app(Herinneringen::class);
    $h->reageer();
    $h->deadline();
    $h->teLaat();
    $h->zoekenLang();
    $h->controleLang();
})->name('herinneringen-overdag')->cron('7,22,37,52 8-20 * * *')->timezone('Europe/Amsterdam')->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes();

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

// Dagoverzicht voor het team: signalen die op "samenvatting" staan, in één mail op werkdagen (besluit Dave 25-11-2026).
Schedule::call(fn () => app(Dagoverzicht::class)->stuur())->name('team-dagoverzicht')->weekdays()->at('07:47')->timezone('Europe/Amsterdam')->withoutOverlapping();

/* Geld, koppelingen en academy (App\Services\Bewaking): elk bericht maar één keer per ding. Tijden: VOORSTEL, 's nachts geen meldingen. */

// Ochtendronde: maandoverzicht en maandscore (alleen op de eerste werkdag), koppelingen die verlopen of weg zijn,
// cursusdagen over 7 dagen en morgen, cursusdagen onder het minimum en tegoed dat aan het eind van de maand over is.
Schedule::call(function () {
    $b = app(Bewaking::class);
    $b->maandoverzicht();
    $b->maandscore();
    $b->koppelingen();
    $b->cursusdagHerinnering();
    $b->academyMinimum();
    $b->tegoedNietBenut();
})->name('bewaking-ochtend')->dailyAt('08:13')->timezone('Europe/Amsterdam')->withoutOverlapping();

// Overdag elk uur: tegoed bijna op en een samengestelde betaalrun die klaarstaat voor vrijgave.
Schedule::call(function () {
    $b = app(Bewaking::class);
    $b->tegoed();
    $b->vrijgave();
})->name('bewaking-overdag')->cron('43 8-20 * * *')->timezone('Europe/Amsterdam')->withoutOverlapping();

// Woensdag: uitbetaalgegevens ontbreken, zodat de creator voor donderdag aanvult en meegaat in de betaalrun van vrijdag.
Schedule::call(fn () => app(Bewaking::class)->gegevensOntbreken())->name('bewaking-uitbetaalgegevens')->wednesdays()->at('10:17')->timezone('Europe/Amsterdam')->withoutOverlapping();
