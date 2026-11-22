<?php

namespace App\Jobs;

use App\Services\Gebruiksrechten;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Dagelijks: gebruiksrechten die over 30 of 7 dagen aflopen melden aan de eigenaar (o-rechten), verlopen rechten afsluiten. */
class MeldGebruiksrechten implements ShouldQueue
{
    use Queueable;

    public function handle(Gebruiksrechten $rechten): void
    {
        $rechten->meld();
    }
}
