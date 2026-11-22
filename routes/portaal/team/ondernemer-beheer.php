<?php

use App\Http\Controllers\Team\OndernemerBeheerController as C;
use Illuminate\Support\Facades\Route;

/* Module ondernemer-beheer, teamkant: detail van een bedrijf. Route-namen team.bedrijfsbeheer.* */

Route::prefix('bedrijven/{bedrijf}')->name('bedrijfsbeheer.')->whereNumber('bedrijf')->group(function () {
    Route::get('/', [C::class, 'bedrijf'])->name('toon');
    Route::put('/', [C::class, 'gegevens'])->name('gegevens');
    Route::put('instellingen', [C::class, 'instellingen'])->name('instellingen');
    Route::post('vestigingen', [C::class, 'vestigingToevoegen'])->name('vestigingen.toevoegen');
    Route::put('vestigingen/{vestiging}', [C::class, 'vestigingBijwerken'])->name('vestigingen.bijwerken');
    Route::post('gebruikers', [C::class, 'uitnodigen'])->name('gebruikers.uitnodigen');
    Route::post('gebruikers/{gebruiker}/opnieuw', [C::class, 'opnieuwUitnodigen'])->name('gebruikers.opnieuw');
    Route::put('gebruikers/{gebruiker}', [C::class, 'rol'])->name('gebruikers.rol');
    Route::delete('gebruikers/{gebruiker}', [C::class, 'verwijderen'])->name('gebruikers.verwijderen');
    Route::post('tegoed', [C::class, 'correctie'])->name('tegoed');
    Route::post('verzoeken/{taak}', [C::class, 'verzoekAfhandelen'])->name('verzoek');
});
