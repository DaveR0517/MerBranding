<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Screening van een aanmelding als creator: de taak in de werkvoorraad en het laatste besluit van het team. */
class ScreeningAanmelding extends Model
{
    protected $table = 'creator_screenings';

    public const GOEDGEKEURD = 'goedgekeurd';

    public const AANVULLING = 'aanvulling';

    public const AFGEWEZEN = 'afgewezen';

    protected $fillable = ['lead_id', 'task_id', 'creator_id', 'decision', 'reason', 'level', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function beslisser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
