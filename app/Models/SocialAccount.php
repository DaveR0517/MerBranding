<?php

namespace App\Models;

use App\Models\Concerns\HeeftHerinneringen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Koppeling van een creator of bedrijf met Instagram of TikTok (fase 3), of van MEBRANDING zelf (eigen kanalen).
 * Tokens staan versleuteld in de database en gaan nooit mee naar het scherm.
 * De eigen kanalen (owner_type EIGEN, owner_id 0) horen bij geen klant: ze vallen buiten elke gewone query,
 * zodat ze niet als klant in lijsten of de koppelingenmonitor verschijnen. Opvragen met SocialAccount::eigen().
 */
class SocialAccount extends Model
{
    use HeeftHerinneringen;

    public const STATUSSEN = ['niet_gekoppeld', 'handmatig', 'gekoppeld', 'verlopen', 'fout'];

    /** Eigenaar van de eigen kanalen van MEBRANDING (geen model, geen klant). */
    public const EIGEN = 'mebranding';

    protected static function booted(): void
    {
        static::addGlobalScope('klanten', fn (Builder $q) => $q->where('owner_type', '!=', self::EIGEN));
    }

    /** Query op de eigen kanalen van MEBRANDING. */
    public static function eigen(): Builder
    {
        return static::withoutGlobalScope('klanten')->where('owner_type', self::EIGEN)->where('owner_id', 0);
    }

    protected $fillable = [
        'owner_type', 'owner_id', 'platform', 'handle', 'external_id', 'scopes', 'profiel', 'professional_confirmed', 'status',
        'access_token', 'refresh_token', 'expires_at', 'token_expires_at', 'last_synced_at', 'last_error', 'consent_version', 'consent_at',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'professional_confirmed' => 'boolean',
            'scopes' => 'array',
            'profiel' => 'array',
            'expires_at' => 'datetime',
            'token_expires_at' => 'datetime',
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

    /** Heeft het platform dit recht verleend bij het koppelen? */
    public function heeftScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
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
