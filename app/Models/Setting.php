<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eén instelling uit beheer. De sleutel is het configpad dat hij overschrijft (zie App\Services\Instellingen). */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'is_proposal', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'json', 'is_proposal' => 'boolean'];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
