<?php

use App\Http\Controllers\Team\CommunicatieController;
use App\Http\Controllers\Team\SalesController;
use Illuminate\Support\Facades\Route;

/* Module communicatie en sales, omgeving team: berichten, support, helpcentrum, sales-pipeline. */

Route::name('communicatie.')->group(function () {
    Route::get('berichten', [CommunicatieController::class, 'inbox'])->name('berichten');
    Route::get('berichten/{samenwerking}', [CommunicatieController::class, 'gesprek'])->name('berichten.gesprek');
    Route::post('berichten/{samenwerking}', [CommunicatieController::class, 'sturen'])->name('berichten.sturen');

    Route::get('support', [CommunicatieController::class, 'supportLijst'])->name('support');
    Route::get('support/{vraag}', [CommunicatieController::class, 'supportLijst'])->name('support.vraag');
    Route::post('support/{vraag}/antwoord', [CommunicatieController::class, 'supportAntwoorden'])->name('support.antwoorden');
    Route::post('support/{vraag}', [CommunicatieController::class, 'supportBijwerken'])->name('support.bijwerken');

    Route::get('help', [CommunicatieController::class, 'help'])->name('help');
    Route::post('help/vraag', [CommunicatieController::class, 'vraagStellen'])->name('help.stellen');
    Route::get('help/vragen/{vraag}', [CommunicatieController::class, 'vraag'])->name('help.vraag');
    Route::post('help/vragen/{vraag}', [CommunicatieController::class, 'reageren'])->name('help.reageren');
    Route::post('help/artikelen', [CommunicatieController::class, 'artikelOpslaan'])->name('help.artikel.nieuw');
    Route::post('help/artikelen/{artikel}', [CommunicatieController::class, 'artikelOpslaan'])->name('help.artikel.opslaan');
    Route::delete('help/artikelen/{artikel}', [CommunicatieController::class, 'artikelVerwijderen'])->name('help.artikel.verwijderen');

    Route::get('sales', [SalesController::class, 'index'])->name('sales');
    Route::post('sales', [SalesController::class, 'toevoegen'])->name('sales.toevoegen');
    Route::post('sales/{lead}/fase', [SalesController::class, 'fase'])->name('sales.fase');
    Route::post('sales/{lead}/notitie', [SalesController::class, 'notitie'])->name('sales.notitie');
    Route::post('sales/{lead}', [SalesController::class, 'bijwerken'])->name('sales.bijwerken');
});
