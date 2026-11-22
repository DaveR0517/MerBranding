<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tot wanneer een gebruiker de berichten van een samenwerking gelezen heeft. */
class BerichtGelezen extends Model
{
    protected $table = 'message_reads';

    public $timestamps = false;

    protected $fillable = ['collaboration_id', 'user_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
