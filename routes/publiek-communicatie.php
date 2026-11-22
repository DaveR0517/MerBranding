<?php

use App\Models\Uitnodigingslink;
use Illuminate\Support\Facades\Route;

/*
| Module communicatie: persoonlijke uitnodigingslink (vriend uitnodigen), zonder inloggen op de website.
| /uitnodiging/{code} bewaart de code in een cookie en stuurt door naar het juiste aanmeldformulier.
| Het websiteformulier (Site\LeadController) leest de cookie en zet de code in leads.data.referral_code.
*/

Route::get('uitnodiging/{code}', function (string $code) {
    $link = Uitnodigingslink::with('user.creator')->where('code', $code)->first();
    if (! $link) {
        return redirect()->route('site.home');
    }

    // Een creator nodigt een creator uit, een ondernemer een ondernemer (vriend-uitnodigen.html).
    $doel = $link->user->creator ? 'site.creators' : 'site.ondernemers';
    $dagen = (int) config('mebranding.uitnodiging.cookie_dagen', 30); // VOORSTEL: 30 dagen geldig

    return redirect()->route($doel)->withCookie(cookie(Uitnodigingslink::COOKIE, $link->code, $dagen * 24 * 60));
})->where('code', '[a-z0-9-]{3,40}')->name('publiek.communicatie.uitnodiging');
