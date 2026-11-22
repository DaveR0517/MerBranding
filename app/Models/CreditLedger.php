<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLedger extends Model
{
    protected $table = 'credit_ledger';

    public const TOEKENNEN = 'toekennen';

    public const RESERVEREN = 'reserveren';

    public const VERBRUIKEN = 'verbruiken';

    public const VRIJGEVEN = 'vrijgeven';

    public const CORRECTIE = 'correctie';

    protected $fillable = ['company_id', 'collaboration_id', 'kind', 'points', 'reason', 'user_id'];

    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }
}
