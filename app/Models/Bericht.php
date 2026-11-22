<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén bericht in een samenwerking. Het kanaal (visible_to) bepaalt wie het ziet:
 * team_ondernemer (ondernemer en team), team_creator (creator en team) of alle (iedereen, alleen met de instelling aan).
 */
class Bericht extends Model
{
    public const TEAM_ONDERNEMER = 'team_ondernemer';

    public const TEAM_CREATOR = 'team_creator';

    public const ALLE = 'alle';

    protected $table = 'messages';

    protected $fillable = ['collaboration_id', 'user_id', 'visible_to', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }
}
