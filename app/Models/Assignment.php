<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Oefenopdracht met een beoordelingslijst: [{label, hint}], elk criterium 1 tot 5. */
class Assignment extends Model
{
    protected $fillable = ['title', 'description', 'criteria', 'position'];

    protected function casts(): array
    {
        return ['criteria' => 'array'];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
