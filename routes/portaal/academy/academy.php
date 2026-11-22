<?php

use App\Http\Controllers\Academy\AcademyController;
use Illuminate\Support\Facades\Route;

/* Omgeving academy (student): /academy, route-namen academy.* */

Route::get('/', [AcademyController::class, 'overzicht'])->name('overzicht');
Route::get('cursusdagen/{dag}', [AcademyController::class, 'cursusdag'])->name('cursusdag');
Route::post('cursusdagen/{dag}/inschrijven', [AcademyController::class, 'inschrijven'])->name('cursusdag.inschrijven');
Route::post('inschrijvingen/{inschrijving}/afmelden', [AcademyController::class, 'afmelden'])->name('inschrijving.afmelden');
Route::post('inschrijvingen/{inschrijving}/ik-ben-er', [AcademyController::class, 'inchecken'])->name('inschrijving.inchecken');
Route::get('lessen/{les}', [AcademyController::class, 'les'])->name('les');
Route::post('lessen/{les}/klaar', [AcademyController::class, 'lesKlaar'])->name('les.klaar');
Route::get('opdrachten/{opdracht}', [AcademyController::class, 'opdracht'])->name('opdracht');
Route::post('opdrachten/{opdracht}', [AcademyController::class, 'opdrachtIndienen'])->name('opdracht.indienen');
Route::get('examen/{examen}', [AcademyController::class, 'examen'])->name('examen');
Route::post('examen/{examen}', [AcademyController::class, 'examenInleveren'])->name('examen.inleveren');
Route::get('certificaat/{certificaat}', [AcademyController::class, 'certificaat'])->name('certificaat');
Route::post('word-creator', [AcademyController::class, 'wordCreator'])->name('word-creator');
Route::get('inzendingen/{inzending}/video', [AcademyController::class, 'video'])->name('inzending.video');
