<?php

namespace App\Models;

use App\Jobs\Geocodeer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = ['company_id', 'name', 'address', 'city', 'experience'];

    /** Nieuw adres: opnieuw op de kaart zetten (App\Services\Geocoder). */
    protected static function booted(): void
    {
        static::created(fn (Location $vestiging) => Geocodeer::dispatch($vestiging));
        static::updated(function (Location $vestiging) {
            if ($vestiging->wasChanged(['address', 'city'])) {
                Geocodeer::dispatch($vestiging);
            }
        });
    }

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'geocoded_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function visitWindows(): HasMany
    {
        return $this->hasMany(VisitWindow::class)->orderBy('weekday')->orderBy('starts_at');
    }
}
