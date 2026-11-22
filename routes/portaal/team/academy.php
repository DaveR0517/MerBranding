<?php

use App\Http\Controllers\Team\AcademyController;
use Illuminate\Support\Facades\Route;

/* Teamportaal, academybeheer (recht academy.beheren): /team/academy, route-namen team.academy.* */

Route::prefix('academy')->name('academy.')->controller(AcademyController::class)->group(function () {
    Route::get('/', 'cursusdagen')->name('cursusdagen');
    Route::post('cursusdagen', 'cursusdagOpslaan')->name('cursusdagen.opslaan');
    Route::get('cursusdagen/{dag}', 'cursusdag')->name('cursusdag');
    Route::put('cursusdagen/{dag}', 'cursusdagBijwerken')->name('cursusdag.bijwerken');
    Route::post('cursusdagen/{dag}/afsluiten', 'afsluiten')->name('cursusdag.afsluiten');
    Route::post('cursusdagen/{dag}/inschrijven', 'inschrijven')->name('cursusdag.inschrijven');
    Route::post('inschrijvingen/{inschrijving}/inchecken', 'inchecken')->name('inschrijving.inchecken');
    Route::post('inschrijvingen/{inschrijving}/betaald', 'betaald')->name('inschrijving.betaald');
    Route::post('inschrijvingen/{inschrijving}/afmelden', 'afmelden')->name('inschrijving.afmelden');
    Route::get('checkin/{code}', 'checkin')->name('checkin'); // de QR-code op het ticket
    Route::post('examens/{examen}/beoordelen', 'examenBeoordelen')->name('examen.beoordelen');
    Route::get('inzendingen', 'inzendingen')->name('inzendingen');
    Route::post('inzendingen/{inzending}/beoordelen', 'inzendingBeoordelen')->name('inzending.beoordelen');
    Route::get('inzendingen/{inzending}/video', 'video')->name('inzending.video');
    Route::get('studenten', 'studenten')->name('studenten');
    Route::post('studenten', 'studentOpslaan')->name('studenten.opslaan');
    Route::get('lesstof', 'lesstof')->name('lesstof');
    Route::post('lessen', 'lesOpslaan')->name('lessen.opslaan');
    Route::put('lessen/{les}', 'lesBijwerken')->name('lessen.bijwerken');
    Route::delete('lessen/{les}', 'lesVerwijderen')->name('lessen.verwijderen');
    Route::post('opdrachten', 'opdrachtOpslaan')->name('opdrachten.opslaan');
    Route::put('opdrachten/{opdracht}', 'opdrachtBijwerken')->name('opdrachten.bijwerken');
    Route::delete('opdrachten/{opdracht}', 'opdrachtVerwijderen')->name('opdrachten.verwijderen');
});
