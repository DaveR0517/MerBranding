<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Eén samenwerking: van aanvraag (1) tot afgerond (11). Statussen wijzigen alleen via App\Services\Samenwerking.
 */
class Collaboration extends Model
{
    protected $fillable = [
        'number', 'company_id', 'location_id', 'creator_id', 'requested_by', 'title', 'briefing',
        'period_from', 'period_to', 'alcohol', 'status', 'points', 'creator_level', 'fee_cents',
        'visit_at', 'visited_at', 'content_deadline_at', 'publish_on', 'live_at', 'measure_until',
        'completed_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'alcohol' => 'boolean',
            'period_from' => 'date',
            'period_to' => 'date',
            'visit_at' => 'datetime',
            'visited_at' => 'datetime',
            'content_deadline_at' => 'datetime',
            'publish_on' => 'date',
            'live_at' => 'datetime',
            'measure_until' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class)->latest();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DeliverableVersion::class)->orderBy('version');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DeliverableVersion::class)->latestOfMany('version');
    }

    public function approvedVersion(): HasOne
    {
        return $this->hasOne(DeliverableVersion::class)->where('outcome', 'goedgekeurd');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function payout(): HasOne
    {
        return $this->hasOne(Payout::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function log(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest('id');
    }

    public function openInvitation(): ?Invitation
    {
        return $this->invitations->first(fn (Invitation $i) => $i->isOpen());
    }
}
