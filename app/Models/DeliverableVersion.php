<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliverableVersion extends Model
{
    protected $fillable = ['collaboration_id', 'version', 'disk', 'path', 'mime', 'size', 'caption', 'label_check', 'submitted_by', 'outcome'];

    protected function casts(): array
    {
        return ['label_check' => 'array'];
    }

    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class)->orderByRaw('timecode is null, timecode');
    }
}
