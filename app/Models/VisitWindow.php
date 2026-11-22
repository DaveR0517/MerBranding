<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitWindow extends Model
{
    protected $fillable = ['location_id', 'weekday', 'starts_at', 'ends_at'];

    public const DAYS = [1 => 'maandag', 2 => 'dinsdag', 3 => 'woensdag', 4 => 'donderdag', 5 => 'vrijdag', 6 => 'zaterdag', 7 => 'zondag'];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function label(): string
    {
        return self::DAYS[$this->weekday].' '.substr($this->starts_at, 0, 5).' - '.substr($this->ends_at, 0, 5);
    }
}
