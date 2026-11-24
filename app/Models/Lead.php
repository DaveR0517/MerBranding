<?php

namespace App\Models;

use App\Jobs\Geocodeer;
use Illuminate\Database\Eloquent\Model;

/**
 * Aanmelding van de website: kennismaking ondernemer of aanmelding creator.
 */
class Lead extends Model
{
    public const TYPE_ONDERNEMER = 'ondernemer';

    public const TYPE_CREATOR = 'creator';

    protected $fillable = [
        'type', 'status', 'name', 'email', 'phone', 'company', 'city',
        'message', 'data', 'consent_at', 'consent_version', 'ip', 'user_agent',
    ];

    /** Een ondernemer die zich aanmeldt, komt op de kaart van het team (creators niet: hun woonplaats blijft privé). */
    protected static function booted(): void
    {
        static::created(function (Lead $lead) {
            if ($lead->isOndernemer() && ($lead->city || ($lead->data['adres'] ?? null))) {
                Geocodeer::dispatch($lead);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'consent_at' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
            'geocoded_at' => 'datetime',
        ];
    }

    public function isOndernemer(): bool
    {
        return $this->type === self::TYPE_ONDERNEMER;
    }
}
