<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eigen notitie of idee op de contentkalender van een bedrijf. */
class ContentNote extends Model
{
    protected $fillable = ['company_id', 'user_id', 'date', 'title', 'body'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
