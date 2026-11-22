<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Exportbestand van een ontwerp (PNG, JPG, webm of MP4), privé opgeslagen onder studio/ op de local disk. */
class StudioExport extends Model
{
    protected $fillable = ['design_id', 'user_id', 'kind', 'page', 'path', 'mime', 'size', 'status', 'message'];

    public function design(): BelongsTo
    {
        return $this->belongsTo(StudioDesign::class, 'design_id');
    }
}
