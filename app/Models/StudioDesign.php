<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ontwerp in MeStudio (eigen marketing van MEBRANDING): lagen per pagina als JSON, met plek in de feedplanner. */
class StudioDesign extends Model
{
    public const STATUSSEN = ['concept', 'klaar', 'ingepland', 'geplaatst'];

    protected $fillable = [
        'user_id', 'template_id', 'name', 'format', 'width', 'height', 'pages', 'status', 'scheduled_at',
        'caption', 'feed_position', 'post_url', 'posted_at', 'preview_path',
    ];

    protected function casts(): array
    {
        return ['pages' => 'array', 'scheduled_at' => 'datetime', 'posted_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(StudioTemplate::class, 'template_id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(StudioExport::class, 'design_id')->latest('id');
    }

    /** Zit er een videolaag (of gevuld videovak) in dit ontwerp? */
    public function heeftVideo(): bool
    {
        foreach ($this->pages ?? [] as $pagina) {
            foreach ($pagina['lagen'] ?? [] as $laag) {
                if (($laag['soort'] ?? null) === 'video' && ! empty($laag['bron'])) {
                    return true;
                }
            }
        }

        return false;
    }
}
