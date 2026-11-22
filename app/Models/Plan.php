<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['name'];

    public function versions(): HasMany
    {
        return $this->hasMany(PlanVersion::class);
    }

    public function currentVersion(): ?PlanVersion
    {
        return $this->versions()->where('valid_from', '<=', now()->toDateString())->latest('valid_from')->first();
    }
}
