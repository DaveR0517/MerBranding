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
        $mag = match ($omgeving) {
            'team' => $user->isTeam(),
            'creator' => $user->creator()->exists(),
            'ondernemer' => $user->companies()->exists(),
            'academy' => $user->hasRole('Student') || $user->creator()->exists(),
            default => false,
        };

        abort_unless($mag, 403, 'Deze omgeving hoort niet bij je account.');

        return $next($request);
    }
}
