<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Publication extends Model
{
    protected $fillable = ['collaboration_id', 'platform', 'url', 'posted_at', 'last_found_at'];

    protected function casts(): array
    {
        return ['posted_at' => 'datetime', 'last_found_at' => 'datetime'];
    }
}
