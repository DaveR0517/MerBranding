<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Factuur voor een ondernemer (fase 6). Bedragen in centen, btw 21%. */
class Invoice extends Model
{
    public const CONCEPT = 'concept';

    public const OPENSTAAND = 'openstaand';

    public const BETAALD = 'betaald';

    protected $fillable = [
        'company_id', 'subscription_id', 'number', 'period_start', 'period_end', 'lines', 'subtotal_cents', 'vat_rate',
        'vat_cents', 'total_cents', 'status', 'issued_on', 'due_on', 'paid_at', 'reminders_sent', 'last_reminder_at',
    ];

    protected function casts(): array
    {
        return [
            'lines' => 'array', 'period_start' => 'date', 'period_end' => 'date', 'issued_on' => 'date', 'due_on' => 'date',
            'paid_at' => 'datetime', 'last_reminder_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isTeLaat(): bool
    {
        return $this->status === self::OPENSTAAND && $this->due_on->lt(today());
    }

    /** Status zoals het scherm hem toont: concept, openstaand, te laat of betaald. */
    public function weergaveStatus(): string
    {
        return $this->isTeLaat() ? 'te_laat' : $this->status;
    }
}
