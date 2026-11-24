<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $fillable = ['key', 'role', 'event', 'channels', 'subject', 'email', 'inapp', 'push'];

    protected function casts(): array
    {
        return ['channels' => 'array', 'email' => 'array'];
    }

    /** Vult {variabelen} in. Onbekende variabelen blijven zichtbaar, zodat ze opvallen. */
    public static function vulIn(?string $text, array $vars): string
    {
        return preg_replace_callback('/\{(\w+)\}/u', fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0], (string) $text);
    }

    public function wants(string $channel): bool
    {
        return in_array($this->channels[$channel] ?? 'uit', ['aan', 'verplicht'], true);
    }
}
