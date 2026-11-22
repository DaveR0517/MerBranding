<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Een reactie op een supportvraag; internal = notitie die alleen het team ziet. */
class SupportAntwoord extends Model
{
    protected $table = 'support_replies';

    protected $fillable = ['support_ticket_id', 'user_id', 'body', 'internal'];

    protected function casts(): array
    {
        return ['internal' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
