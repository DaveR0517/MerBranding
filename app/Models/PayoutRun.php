<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** De wekelijkse betaalrun op vrijdag (W7). Betaalpartij nog niet gekozen: uitbetalen via export. */
class PayoutRun extends Model
{
    public const SAMENGESTELD = 'samengesteld';

    public const UITBETAALD = 'uitbetaald';

    protected $fillable = ['run_on', 'status', 'total_cents', 'composed_by', 'exported_at', 'paid_at', 'paid_by'];

    protected function casts(): array
    {
        return ['run_on' => 'date', 'exported_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    /** Uitbetalingen in deze run. Payout zelf blijft ongewijzigd: de koppeling loopt via payout_run_id. */
    public function payouts()
    {
        return Payout::where('payout_run_id', $this->id);
    }
}
