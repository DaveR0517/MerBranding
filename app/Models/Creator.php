<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Creator extends Model
{
    protected $fillable = ['user_id', 'level', 'kind', 'niches', 'birthdate', 'city', 'instagram', 'tiktok', 'vat_status', 'payout_details', 'status'];

    protected $hidden = ['payout_details'];

    protected function casts(): array
    {
        return ['niches' => 'array', 'birthdate' => 'date', 'payout_details' => 'encrypted'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collaborations(): HasMany
    {
        return $this->hasMany(Collaboration::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function levelName(): string
    {
        return config("mebranding.levels.{$this->level}.name", ucfirst($this->level));
    }

    public function age(): ?int
    {
        return $this->birthdate?->age;
    }

    /** Alleen Platinum en Elite krijgen een vergoeding. */
    public function feeCents(): int
    {
        return (int) config("mebranding.levels.{$this->level}.fee_cents", 0);
    }

    public function points(): int
    {
        return (int) config("mebranding.levels.{$this->level}.points", 0);
    }
}
