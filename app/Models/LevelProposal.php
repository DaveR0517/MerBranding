<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Voorstel tot niveauwijziging. Het systeem stelt voor, een teamlid met klassement.beheren beslist met reden. */
class LevelProposal extends Model
{
    protected $fillable = ['creator_id', 'from_level', 'to_level', 'score', 'reason', 'status', 'decided_by', 'decided_at', 'decision_reason'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'score' => 'float'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
