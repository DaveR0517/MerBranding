<?php

namespace App\Http\Controllers\Ondernemer;

use App\Http\Controllers\Communicatie\CommunicatieBasis;
use App\Models\Collaboration;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Berichten, help, support en vriend uitnodigen voor de ondernemer. */
class CommunicatieController extends CommunicatieBasis
{
    protected function omgeving(): string
    {
        return 'ondernemer';
    }

    protected function samenwerkingen(User $user): Builder
    {
        return Collaboration::query()->whereIn('company_id', $user->companies()->pluck('companies.id'));
    }

    protected function samenwerkingUrl(Collaboration $c): string
    {
        return route('ondernemer.samenwerking', $c);
    }
}
