<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eén apparaat dat pushmeldingen ontvangt (webapp op de telefoon of browser op de computer). */
class PushAbonnement extends Model
{
    protected $table = 'push_abonnementen';

    protected $fillable = ['user_id', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'apparaat', 'laatst_gebruikt_op'];

    protected function casts(): array
    {
        return ['laatst_gebruikt_op' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
