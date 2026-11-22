<?php

use App\Http\Controllers\Ondernemer\CommunicatieController;
use Illuminate\Support\Facades\Route;

/* Module communicatie, omgeving ondernemer: berichten per samenwerking, help en support, vriend uitnodigen. */

Route::name('communicatie.')->group(function () {
    Route::get('berichten', [CommunicatieController::class, 'inbox'])->name('berichten');
    Route::get('berichten/{samenwerking}', [CommunicatieController::class, 'gesprek'])->name('berichten.gesprek');
    Route::post('berichten/{samenwerking}', [CommunicatieController::class, 'sturen'])->name('berichten.sturen');

    Route::get('help', [CommunicatieController::class, 'help'])->name('help');
    Route::post('help/vraag', [CommunicatieController::class, 'vraagStellen'])->name('help.stellen');
    Route::get('help/vragen/{vraag}', [CommunicatieController::class, 'vraag'])->name('help.vraag');
    Route::post('help/vragen/{vraag}', [CommunicatieController::class, 'reageren'])->name('help.reageren');

    Route::get('uitnodigen', [CommunicatieController::class, 'uitnodigen'])->name('uitnodigen');
});
