<?php

namespace App\Listeners;

use App\Events\SamenwerkingGewijzigd;
use App\Models\CreatorProfiel;
use App\Models\Status;
use App\Services\Geld;
use App\Services\Melder;

/**
 * Bij Live: heeft de creator recht op geld maar ontbreken zijn uitbetaalgegevens of btw-status,
 * dan krijgt hij de melding c-gegevens-ontbreken (docs/02 flow 2, timer "Uitbetaalgegevens ontbreken").
 */
class UitbetaalgegevensControleren
{
    public function __construct(private Melder $melder) {}

    public function handle(SamenwerkingGewijzigd $event): void
    {
        $c = $event->samenwerking;
        if ($event->naar !== Status::Live || $c->fee_cents <= 0 || ! $c->creator) {
            return;
        }

        $creator = $c->creator;
        if (CreatorProfiel::uitbetalingCompleet($creator)) {
            return;
        }

        $this->melder->stuur('c-gegevens-ontbreken', $creator->user, [
            'bedrag' => Geld::euro($c->fee_cents),
        ], route('creator.profiel.account'));
    }
}
