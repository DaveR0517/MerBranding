<?php

namespace App\Listeners;

use App\Services\Melder;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * Na een gekozen wachtwoord via een mail-link:
 * - eerste keer (uitnodiging): het e-mailadres is daarmee bewezen, geen aparte bevestigingsmail nodig;
 * - daarna (wachtwoord vergeten): beveiligingsmail "Je wachtwoord is gewijzigd" (g-beveiliging).
 */
class NaWachtwoordReset
{
    public function __construct(private Melder $melder) {}

    public function handle(PasswordReset $event): void
    {
        if ($event->user instanceof MustVerifyEmail && ! $event->user->hasVerifiedEmail()) {
            $event->user->markEmailAsVerified();

            return;
        }

        $this->melder->wachtwoordGewijzigd($event->user);
    }
}
