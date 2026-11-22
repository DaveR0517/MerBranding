<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Artikel in het helpcentrum (markdown), per doelgroep: ondernemer, creator, team. */
class HelpArtikel extends Model
{
    public const DOELGROEPEN = ['ondernemer', 'creator', 'team'];

    protected $table = 'help_articles';

    protected $fillable = ['slug', 'title', 'category', 'body', 'audiences', 'position'];

    protected function casts(): array
    {
        return ['audiences' => 'array'];
    }

    public function scopeVoor(Builder $query, string $doelgroep): Builder
    {
        return $query->whereJsonContains('audiences', $doelgroep)->orderBy('position')->orderBy('title');
    }

    /** Zoeken in titel en inhoud. */
    public function scopeZoek(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $query : $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhere('body', 'like', "%{$term}%"));
    }
}
