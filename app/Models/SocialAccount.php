<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Koppeling van een creator of bedrijf met Instagram of TikTok (fase 3).
 * Tokens staan versleuteld in de database en gaan nooit mee naar het scherm.
 */
class SocialAccount extends Model
{
    public const STATUSSEN = ['niet_gekoppeld', 'handmatig', 'gekoppeld', 'verlopen', 'fout'];

    protected $fillable = [
        'owner_type', 'owner_id', 'platform', 'handle', 'professional_confirmed', 'status',
        'access_token', 'refresh_token', 'expires_at', 'last_synced_at', 'last_error', 'consent_version', 'consent_at',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'professional_confirmed' => 'boolean',
            'expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'consent_at' => 'datetime',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** Toestemming gegeven voor de huidige versie van de toestemmingstekst. */
    public function hasCurrentConsent(): bool
    {
        return $this->consent_at !== null && $this->consent_version === config('content.consent_version');
    }

    /** Waarschuwing voor de monitor: verloopt binnenkort, verlopen of fout. */
    public function warning(): ?string
    {
        return match (true) {
            $this->status === 'fout' => 'fout',
            $this->status === 'verlopen', $this->expires_at?->isPast() => 'verlopen',
            $this->expires_at !== null && $this->expires_at->lte(now()->addDays((int) config('content.koppelingen.warn_days'))) => 'verloopt',
            default => null,
        };
    }
}
