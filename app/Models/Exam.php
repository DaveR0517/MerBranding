<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Praktijkexamen bij een inschrijving: video's inleveren, beoordeling per criterium (config academy.exam_criteria). */
class Exam extends Model
{
    public const OPEN = 'open';

    public const INGELEVERD = 'ingeleverd';

    public const VOLDOENDE = 'voldoende';

    public const VERBETEREN = 'verbeteren';

    public const ONVOLDOENDE = 'onvoldoende';

    protected $fillable = ['enrollment_id', 'status', 'attempt', 'due_at', 'submitted_at', 'scores', 'average', 'feedback', 'assessor_id', 'assessed_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'submitted_at' => 'datetime', 'assessed_at' => 'datetime', 'scores' => 'array', 'average' => 'float'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /** Mag de student (opnieuw) inleveren? */
    public function kanInleveren(): bool
    {
        return in_array($this->status, [self::OPEN, self::VERBETEREN], true);
    }
}
