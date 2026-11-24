<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = ['type', 'title', 'collaboration_id', 'company_id', 'creator_id', 'permission', 'owner_id', 'due_at', 'closed_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    /**
     * Menupagina waar een taaksoort zonder samenwerking thuishoort (teller in het menu).
     * Taken bij een samenwerking horen bij Samenwerkingen; onbekende soorten alleen bij de Werkvoorraad.
     */
    public const MENU = [
        'screening' => 'team.screening.index',
        'support' => 'team.communicatie.support',
        'cijfers-controleren' => 'team.cijfers',
        'abonnement' => 'team.bedrijven',
        'rechten_verlengen' => 'team.content.bedrijf',
        'radar_interesse' => 'team.content.radar',
    ];

    public function menuRoute(): ?string
    {
        return $this->collaboration_id ? 'team.samenwerkingen' : (self::MENU[$this->type] ?? null);
    }

    /** Taken die deze gebruiker mag oppakken: zonder recht voor iedereen, anders met dat recht. Eén bron voor werkvoorraad en tellers. */
    public function scopeVoor(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        return $query->where(fn ($w) => $w->whereNull('permission')->orWhereIn('permission', $user->getAllPermissions()->pluck('name')));
    }
}
