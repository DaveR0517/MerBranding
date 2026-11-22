<?php

use App\Http\Controllers\Site\AcademyController;
use Illuminate\Support\Facades\Route;

/* Publiek (website, zonder inloggen): controle van een academy-certificaat via de controlelink. */

Route::get('certificaat/{code}', [AcademyController::class, 'controle'])->name('publiek.academy.certificaat')->where('code', '[A-Za-z0-9]{16,40}');
