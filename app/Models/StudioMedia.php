<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bestand in de mediatheek van MeStudio: foto, video of logo, privé op de local disk. */
class StudioMedia extends Model
{
    public const SOORTEN = ['foto', 'video', 'logo'];

    protected $table = 'studio_media';

    protected $fillable = ['user_id', 'kind', 'name', 'disk', 'path', 'mime', 'size', 'width', 'height'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
