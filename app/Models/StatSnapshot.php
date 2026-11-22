<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén ruwe meting van één cijfer van een publicatie (CLAUDE.md 8): bron, periode, ophaaldatum en of het een schatting is.
 * Nooit bijwerken: een correctie van het team is een nieuwe regel die de oude vervangt (replaces_id).
 */
class StatSnapshot extends Model
{
    public const HANDMATIG = 'handmatig';

    public const BRONNEN = ['handmatig', 'instagram', 'tiktok'];

    protected $fillable = [
        'publication_id', 'metric', 'value', 'source', 'period', 'fetched_at', 'is_estimate',
        'screenshot_path', 'entered_by', 'replaces_id', 'note', 'reviewed_at', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime', 'reviewed_at' => 'datetime', 'is_estimate' => 'boolean', 'value' => 'integer'];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    /** Regels die niet door een correctie zijn vervangen. */
    public function scopeGeldig(Builder $q): Builder
    {
        return $q->whereNotIn('id', self::query()->whereNotNull('replaces_id')->select('replaces_id'));
    }
}
