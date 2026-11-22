<?php

use App\Http\Controllers\Ondernemer\OndernemerBeheerController as C;
use Illuminate\Support\Facades\Route;

/* Module ondernemer-beheer: vestigingen, gebruikers, abonnement en "Aan de slag". Route-namen ondernemer.beheer.* */

Route::name('beheer.')->group(function () {
    Route::get('vestigingen', [C::class, 'vestigingen'])->name('vestigingen');
    Route::post('vestigingen', [C::class, 'vestigingToevoegen'])->name('vestigingen.toevoegen');
    Route::put('vestigingen/{vestiging}', [C::class, 'vestigingBijwerken'])->name('vestigingen.bijwerken');
    Route::put('bedrijfsinstellingen', [C::class, 'instellingen'])->name('instellingen');

    Route::get('gebruikers', [C::class, 'gebruikers'])->name('gebruikers');
    Route::post('gebruikers', [C::class, 'uitnodigen'])->name('gebruikers.uitnodigen');
    Route::post('gebruikers/{gebruiker}/opnieuw', [C::class, 'opnieuwUitnodigen'])->name('gebruikers.opnieuw');
    Route::put('gebruikers/{gebruiker}', [C::class, 'rol'])->name('gebruikers.rol');
    Route::delete('gebruikers/{gebruiker}', [C::class, 'verwijderen'])->name('gebruikers.verwijderen');

    Route::get('abonnement', [C::class, 'abonnement'])->name('abonnement');
    Route::post('abonnement/verzoek', [C::class, 'abonnementVerzoek'])->name('abonnement.verzoek');

    Route::get('aan-de-slag', [C::class, 'welkom'])->name('welkom');
    Route::post('aan-de-slag/sluiten', [C::class, 'welkomSluiten'])->name('welkom.sluiten');
    Route::post('aan-de-slag/{stap}', [C::class, 'stap'])->name('welkom.stap');
});
