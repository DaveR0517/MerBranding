<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = ['company_id', 'name', 'address', 'city', 'experience'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function visitWindows(): HasMany
    {
        return $this->hasMany(VisitWindow::class)->orderBy('weekday')->orderBy('starts_at');
    }
}
