<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gebruiksrecht op een video: organisch of in advertenties, met start en einde.
 * Open punt 7 (rechten per pakket) is niet besloten: de standaardduur is een VOORSTEL (config/content.php).
 */
class UsageRight extends Model
{
    public const SOORTEN = ['organisch', 'advertentie'];

    public const STATUSSEN = ['actief', 'verlopen', 'ingetrokken'];

    protected $fillable = [
        'collaboration_id', 'deliverable_version_id', 'kind', 'starts_on', 'ends_on', 'ad_code', 'status',
        'is_proposal', 'notified', 'extension_requested_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_proposal' => 'boolean',
            'notified' => 'array',
            'extension_requested_at' => 'datetime',
        ];
    }

    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }

    /** Alleen rechten van samenwerkingen van dit bedrijf. */
    public function scopeVoorBedrijf(Builder $query, int $companyId): Builder
    {
        return $query->whereHas('collaboration', fn ($q) => $q->where('company_id', $companyId));
    }

    public function daysLeft(): int
    {
        return (int) today()->diffInDays($this->ends_on, false);
    }
}
