<?php

namespace App\Providers;

use App\Models\Lead;
use App\Services\Screening;
use Illuminate\Support\ServiceProvider;

/** Module creator-profiel: elke nieuwe aanmelding als creator krijgt een screeningtaak in de werkvoorraad. */
class CreatorProfielServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Status is leeg als de database de standaardwaarde (nieuw) invult.
        Lead::created(function (Lead $lead) {
            if ($lead->type === Lead::TYPE_CREATOR && in_array($lead->status, [null, 'nieuw'], true)) {
                app(Screening::class)->aangemeld($lead);
            }
        });
    }
}
