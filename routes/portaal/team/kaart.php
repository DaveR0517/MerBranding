<?php

use App\Http\Controllers\Team\KaartController;
use Illuminate\Support\Facades\Route;

/* Module kaart, omgeving team: aangesloten zaken, aanmeldingen en open klussen op de kaart (/team/kaart). */
Route::get('kaart', [KaartController::class, 'index'])->name('kaart');
