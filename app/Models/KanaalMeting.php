<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ruwe dagwaarde van een eigen kanaal (volgers, weergaven, bereik ...). Eén rij per account, dag en metriek. */
class KanaalMeting extends Model
{
    protected $table = 'kanaal_metingen';

    protected $fillable = ['social_account_id', 'datum', 'metriek', 'waarde'];

    protected function casts(): array
    {
        return ['datum' => 'date', 'waarde' => 'integer'];
    }

    public static function zet(int $accountId, string $datum, string $metriek, int $waarde): void
    {
        // whereDate: sqlite bewaart een datum met tijd, MySQL zonder; zo vinden we de rij van die dag altijd terug.
        $rij = static::where('social_account_id', $accountId)->whereDate('datum', $datum)->where('metriek', $metriek)->first();
        $rij ? $rij->update(['waarde' => $waarde]) : static::create(['social_account_id' => $accountId, 'datum' => $datum, 'metriek' => $metriek, 'waarde' => $waarde]);
    }
}
