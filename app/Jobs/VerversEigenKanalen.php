<?php

namespace App\Jobs;

use App\Services\Kanalen\EigenKanalen;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Elk uur: profiel, posts, cijfers en gesprekken van de eigen kanalen ophalen en tokens op tijd verlengen. */
class VerversEigenKanalen implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function handle(EigenKanalen $kanalen): void
    {
        $kanalen->verversAlles();
    }
}
