<?php

namespace App\Events;

use App\Models\Collaboration;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Na elke statuswijziging van een samenwerking. Modules (score, cijfers, berichten, academy) luisteren hierop
 * met een eigen listener in app/Listeners, zodat de kern (App\Services\Samenwerking) niet aangepast hoeft te worden.
 */
class SamenwerkingGewijzigd
{
    use Dispatchable;

    public function __construct(
        public Collaboration $samenwerking,
        public ?Status $van,
        public Status $naar,
        public ?User $door,
    ) {}
}
