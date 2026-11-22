<?php

namespace App\Models;

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

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'consent_at' => 'datetime',
        ];
    }

    public function isOndernemer(): bool
    {
        return $this->type === self::TYPE_ONDERNEMER;
    }
}
