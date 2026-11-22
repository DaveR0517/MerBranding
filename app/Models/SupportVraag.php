<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Een supportvraag van een ondernemer, creator of teamlid. */
class SupportVraag extends Model
{
    public const STATUSSEN = ['open' => 'Open', 'beantwoord' => 'Beantwoord', 'gesloten' => 'Gesloten'];

    public const PRIORITEITEN = ['laag' => 'Laag', 'normaal' => 'Normaal', 'hoog' => 'Hoog'];

    protected $table = 'support_tickets';

    protected $fillable = ['number', 'user_id', 'portal', 'company_id', 'collaboration_id', 'assigned_to', 'subject', 'status', 'priority', 'closed_at'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportAntwoord::class, 'support_ticket_id')->oldest();
    }
}
