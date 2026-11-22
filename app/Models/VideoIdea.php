<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Video-idee van een bedrijf, van de ondernemer of het team. Het team kan er bij een aanvraag naar verwijzen. */
class VideoIdea extends Model
{
    public const STATUSSEN = ['idee', 'gepland', 'gebruikt'];

    protected $fillable = ['company_id', 'user_id', 'collaboration_id', 'title', 'description', 'status', 'source'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }
}
