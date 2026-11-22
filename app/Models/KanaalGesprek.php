<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Gesprek in de Instagram-inbox van MEBRANDING, per persoon (Instagram-scoped id). */
class KanaalGesprek extends Model
{
    protected $table = 'kanaal_gesprekken';

    protected $fillable = ['social_account_id', 'extern_id', 'deelnemer_id', 'deelnemer_naam', 'laatste_bericht_op', 'laatste_inkomend_op', 'ongelezen'];

    protected function casts(): array
    {
        return ['laatste_bericht_op' => 'datetime', 'laatste_inkomend_op' => 'datetime', 'ongelezen' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id')->withoutGlobalScope('klanten');
    }

    public function berichten(): HasMany
    {
        return $this->hasMany(KanaalBericht::class)->orderBy('verzonden_op');
    }

    /** Tot wanneer Meta een antwoord toestaat (24 uur na het laatste bericht van de ander), of null als dat voorbij is. */
    public function antwoordenTot(): ?CarbonInterface
    {
        $tot = $this->laatste_inkomend_op?->copy()->addHours((int) config('kanalen.berichtvenster_uren'));

        return $tot?->isFuture() ? $tot : null;
    }
}
