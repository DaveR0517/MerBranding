<?php

namespace App\Listeners;

use App\Models\CompanyOnboarding;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Een ondernemer die zijn startstappen nog niet heeft afgerond, komt na het inloggen op het welkomstscherm.
 * Een eerder gevraagde specifieke pagina (bijvoorbeeld uit een mail) gaat voor; de startpagina niet.
 */
class WelkomNaInloggen
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User || ! app()->bound('session') || $user->homePortal() !== 'ondernemer') {
            return;
        }

        $gevraagd = session('url.intended');
        $startpaginas = [route('start'), route('dashboard'), route('ondernemer.overzicht')];
        if ($gevraagd && ! in_array(rtrim($gevraagd, '/'), array_map(fn ($u) => rtrim($u, '/'), $startpaginas), true)) {
            return;
        }

        $bedrijf = $user->companies()->first();
        if ($bedrijf && ! CompanyOnboarding::voor($bedrijf)->klaar()) {
            session()->put('url.intended', route('ondernemer.beheer.welkom'));
        }
    }
}
