<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Certificaat met uniek nummer (MB-ACA-jaar-volgnummer) en een geheime controlecode voor de publieke controlelink. */
class Certificate extends Model
{
    protected $fillable = ['enrollment_id', 'user_id', 'number', 'verification_code', 'issued_at', 'issued_by'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function controleUrl(): string
    {
        return route('publiek.academy.certificaat', $this->verification_code);
    }
}
