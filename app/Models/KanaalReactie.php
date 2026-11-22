<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reactie onder een post van het eigen Instagram-account (of een antwoord van MEBRANDING daarop). */
class KanaalReactie extends Model
{
    protected $table = 'kanaal_reacties';

    protected $fillable = ['kanaal_media_id', 'extern_id', 'ouder_extern_id', 'gebruikersnaam', 'tekst', 'likes', 'eigen', 'geplaatst_op', 'user_id'];

    protected function casts(): array
    {
        return ['geplaatst_op' => 'datetime', 'eigen' => 'boolean', 'likes' => 'integer'];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(KanaalMedia::class, 'kanaal_media_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
