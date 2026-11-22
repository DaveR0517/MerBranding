<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $table = 'feedback';

    protected $fillable = ['deliverable_version_id', 'user_id', 'timecode', 'body', 'visible_to_creator'];

    protected function casts(): array
    {
        return ['visible_to_creator' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DeliverableVersion::class, 'deliverable_version_id');
    }
}
