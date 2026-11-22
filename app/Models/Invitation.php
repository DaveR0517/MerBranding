<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $fillable = ['collaboration_id', 'creator_id', 'invited_by', 'expires_at', 'answer', 'answered_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'answered_at' => 'datetime'];
    }

    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public function isOpen(): bool
    {
        return $this->answer === null && $this->expires_at->isFuture();
    }
}
