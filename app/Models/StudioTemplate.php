<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sjabloon in MeStudio: een ontwerp met benoemde vakken (foto, video, tekst) dat als begin dient voor een nieuw ontwerp. */
class StudioTemplate extends Model
{
    protected $fillable = ['slug', 'user_id', 'name', 'format', 'width', 'height', 'pages', 'preview_path', 'is_builtin', 'archived_at'];

    protected function casts(): array
    {
        return ['pages' => 'array', 'is_builtin' => 'boolean', 'archived_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActief(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
