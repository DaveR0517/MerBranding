<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gebeurtenis die de score uitlegt: een strafpunt op Betrouwbaarheid (niet_gekomen, te_laat, post_weg)
 * of een kwaliteitsoordeel van het team (beoordeling, 0 tot 100). Weigeren van een uitnodiging komt hier nooit in.
 */
class ScoreEvent extends Model
{
    public const NIET_GEKOMEN = 'niet_gekomen';

    public const TE_LAAT = 'te_laat';

    public const POST_WEG = 'post_weg';

    public const BEOORDELING = 'beoordeling';

    protected $fillable = ['creator_id', 'collaboration_id', 'kind', 'reason', 'points', 'decided_by'];

    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public static function label(string $kind): string
    {
        return match ($kind) {
            self::NIET_GEKOMEN => 'Niet gekomen na toezegging',
            self::TE_LAAT => 'Te laat',
            self::POST_WEG => 'Post te vroeg weg',
            self::BEOORDELING => 'Kwaliteitsoordeel team',
            default => $kind,
        };
    }
}
