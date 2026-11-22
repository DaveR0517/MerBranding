<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Inzending van een student: bij een oefenopdracht of een video van het praktijkexamen. Link of eigen video. */
class Submission extends Model
{
    protected $fillable = ['user_id', 'assignment_id', 'exam_id', 'attempt', 'label', 'link', 'disk', 'path', 'mime', 'note', 'scores', 'feedback', 'assessor_id', 'assessed_at'];

    protected function casts(): array
    {
        return ['scores' => 'array', 'assessed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }
}
