<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aanpassingen op de merkkit van MeStudio (één rij). Lees altijd via App\Services\Studio\Merkkit. */
class StudioMerkkit extends Model
{
    protected $table = 'studio_merkkit';

    protected $fillable = ['data', 'user_id'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
