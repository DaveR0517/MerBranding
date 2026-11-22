<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eén Instagram-bericht: in (van de ander) of uit (van MEBRANDING, met wie het stuurde). */
class KanaalBericht extends Model
{
    protected $table = 'kanaal_berichten';

    protected $fillable = ['kanaal_gesprek_id', 'extern_id', 'richting', 'tekst', 'bijlagen', 'verzonden_op', 'user_id'];

    protected function casts(): array
    {
        return ['verzonden_op' => 'datetime', 'bijlagen' => 'array'];
    }

    public function gesprek(): BelongsTo
    {
        return $this->belongsTo(KanaalGesprek::class, 'kanaal_gesprek_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
