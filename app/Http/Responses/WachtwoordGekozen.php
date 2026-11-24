<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\PasswordResetResponse;

/**
 * Na het kiezen van een wachtwoord via een uitnodigings- of resetlink: direct ingelogd en naar de eigen omgeving.
 * De link bewijst al dat het e-mailadres van deze persoon is. Wie tweestapsverificatie aanzette, gaat via het
 * inlogscherm, zodat die tweede stap nooit wordt overgeslagen.
 */
class WachtwoordGekozen implements PasswordResetResponse
{
    public function toResponse($request): RedirectResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if ($user && ! $user->two_factor_confirmed_at) {
            Auth::guard()->login($user);
            $request->session()->regenerate();

            return redirect()->route('start')->with('melding', 'Je wachtwoord is opgeslagen. Welkom bij MEBRANDING!');
        }

        return redirect()->route('login')->with('status', trans(Password::PASSWORD_RESET));
    }
}
