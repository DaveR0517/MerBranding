<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Communicatie\CommunicatieBasis;
use App\Models\Collaboration;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Berichten, help, support en vriend uitnodigen voor de creator. Alleen klussen waaraan de creator gekoppeld is. */
class CommunicatieController extends CommunicatieBasis
{
    protected function omgeving(): string
    {
        return 'creator';
    }

    protected function samenwerkingen(User $user): Builder
    {
        return Collaboration::query()->where('creator_id', $user->creator?->id ?? 0);
    }

    protected function samenwerkingUrl(Collaboration $c): string
    {
        return route('creator.klus', $c);
    }
}
