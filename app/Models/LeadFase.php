<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Waar een kennismaking staat in de sales-pipeline, met eigenaar en volgende actie. */
class LeadFase extends Model
{
    public const FASES = [
        'nieuw' => 'Nieuw',
        'gebeld' => 'Gebeld',
        'afspraak' => 'Afspraak',
        'offerte' => 'Offerte',
        'klant' => 'Klant',
        'verloren' => 'Verloren',
    ];

    protected $table = 'lead_stages';

    protected $fillable = ['lead_id', 'stage', 'owner_id', 'next_action', 'next_action_on'];

    protected function casts(): array
    {
        return ['next_action_on' => 'date'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
