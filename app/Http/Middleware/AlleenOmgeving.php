<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Laat alleen gebruikers van één omgeving door: team, creator, ondernemer of academy. */
class AlleenOmgeving
{
    public function handle(Request $request, Closure $next, string $omgeving): Response
    {
        $user = $request->user();
        abort_unless($user->magOmgeving($omgeving), 403, 'Deze omgeving hoort niet bij je account.');

        // Onthoud de omgeving waarin je werkt: het menu volgt die, ook op gedeelde pagina's (meldingen, instellingen).
        if (in_array($omgeving, $user->omgevingen(), true)) {
            $request->session()->put('omgeving', $omgeving);
        }

        return $next($request);
    }
}
