<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Berekende score van een creator over een periode (90 dagen). Berekend door App\Services\Score. */
class Score extends Model
{
    protected $fillable = [
        'creator_id', 'period_start', 'period_end', 'reach', 'engagement', 'ad_result', 'quality', 'reliability',
        'total', 'jobs_count', 'is_new_talent', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'calculated_at' => 'datetime', 'is_new_talent' => 'boolean',
            'reach' => 'float', 'engagement' => 'float', 'ad_result' => 'float', 'quality' => 'float', 'reliability' => 'float', 'total' => 'float',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    /** Laatste score per creator. */
    public static function laatste(int $creatorId): ?self
    {
        return self::where('creator_id', $creatorId)->latest('period_end')->first();
    }
}
