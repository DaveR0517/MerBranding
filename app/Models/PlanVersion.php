<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanVersion extends Model
{
    protected $fillable = ['plan_id', 'price_cents', 'points_per_month', 'valid_from', 'is_proposal'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'is_proposal' => 'boolean'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
