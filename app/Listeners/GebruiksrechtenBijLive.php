<?php

namespace App\Listeners;

use App\Events\SamenwerkingGewijzigd;
use App\Models\Status;
use App\Services\Gebruiksrechten;

/** Zodra een samenwerking live staat, starten de standaard gebruiksrechten (VOORSTEL, open punt 7). */
class GebruiksrechtenBijLive
{
    public function __construct(private Gebruiksrechten $rechten) {}

    public function handle(SamenwerkingGewijzigd $event): void
    {
        if ($event->naar === Status::Live) {
            $this->rechten->standaard($event->samenwerking);
        }
    }
}
