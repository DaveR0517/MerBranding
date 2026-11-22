<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Signature extends Model
{
    protected $fillable = ['user_id', 'collaboration_id', 'agreement', 'version', 'signed_name', 'ip'];
}
