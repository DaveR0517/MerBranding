<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Laat alleen gebruikers van één omgeving door: team, creator of ondernemer. Super Admin mag overal kijken. */
class AlleenOmgeving
{
    public function handle(Request $request, Closure $next, string $omgeving): Response
    {
        $user = $request->user();
        $mag = match ($omgeving) {
            'team' => $user->isTeam(),
            'creator' => $user->creator()->exists(),
            'ondernemer' => $user->companies()->exists(),
            default => false,
        };

        abort_unless($mag, 403, 'Deze omgeving hoort niet bij je account.');

        return $next($request);
    }
}
