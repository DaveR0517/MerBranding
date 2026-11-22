<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Post of video op een eigen kanaal van MEBRANDING, met de laatste ruwe cijfers van de API. */
class KanaalMedia extends Model
{
    protected $table = 'kanaal_media';

    protected $fillable = ['social_account_id', 'extern_id', 'soort', 'product', 'bijschrift', 'media_url', 'miniatuur_url', 'permalink', 'duur', 'geplaatst_op', 'cijfers', 'cijfers_op'];

    protected function casts(): array
    {
        return ['geplaatst_op' => 'datetime', 'cijfers_op' => 'datetime', 'cijfers' => 'array'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id')->withoutGlobalScope('klanten');
    }

    public function reacties(): HasMany
    {
        return $this->hasMany(KanaalReactie::class)->orderBy('geplaatst_op');
    }

    public function cijfer(string $naam): ?int
    {
        return isset($this->cijfers[$naam]) ? (int) $this->cijfers[$naam] : null;
    }
}
