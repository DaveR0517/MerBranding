<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Team heeft tweestapsverificatie verplicht (CLAUDE.md 5). Een teamlid zonder bevestigde tweestaps gaat eerst
 * naar de beveiligingspagina. Uit te zetten met de instelling mebranding.team_2fa_verplicht (Instellingen, Super Admin).
 */
class TweestapsVerplicht
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Bij "Inloggen als" kijkt een Super Admin mee; die heeft zelf al ingelogd.
        $meekijken = $request->hasSession() && $request->session()->has('inloggen_als_van');

        if (config('mebranding.team_2fa_verplicht') && ! $meekijken && $user?->isTeam() && ! $user->two_factor_confirmed_at) {
            return redirect()->route('security.edit')
                ->with('melding', 'Zet eerst tweestapsverificatie aan. Dat is verplicht voor iedereen in het team.');
        }

        return $next($request);
    }
}
