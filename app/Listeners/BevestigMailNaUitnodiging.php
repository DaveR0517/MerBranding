<?php

namespace App\Listeners;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * Een uitgenodigde gebruiker kiest zijn wachtwoord via de link in de mail. Daarmee is het e-mailadres bewezen,
 * dus hoeft hij niet nog eens een bevestigingsmail te openen.
 */
class BevestigMailNaUitnodiging
{
    public function handle(PasswordReset $event): void
    {
        if ($event->user instanceof MustVerifyEmail && ! $event->user->hasVerifiedEmail()) {
            $event->user->markEmailAsVerified();
        }
    }
}
