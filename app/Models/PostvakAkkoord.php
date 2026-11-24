<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Akkoord van de eigenaar van een persoonlijk postvak dat de eigenaren van MEBRANDING het in het portaal mogen zien. */
class PostvakAkkoord extends Model
{
    protected $table = 'postvak_akkoorden';

    protected $fillable = ['postvak', 'user_id', 'akkoord_op', 'ingetrokken_op', 'ip'];

    protected function casts(): array
    {
        return ['akkoord_op' => 'datetime', 'ingetrokken_op' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function geldig(): bool
    {
        return $this->ingetrokken_op === null;
    }
}
