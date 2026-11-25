<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $fillable = ['key', 'role', 'event', 'vorm', 'channels', 'subject', 'email', 'inapp', 'push', 'standaard_hash'];

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

    /** Mail alleen als vangnet: de ontvanger krijgt hem alleen als er geen pushmelding aankomt. */
    public function vangnet(): bool
    {
        return ($this->channels['email'] ?? null) === 'vangnet';
    }

    /** Vingerafdruk van de inhoud, ongevoelig voor de volgorde van sleutels (de database sorteert JSON zelf). */
    public static function vingerafdruk(array $inhoud): string
    {
        $orden = function ($v) use (&$orden) {
            if (! is_array($v)) {
                return $v;
            }
            if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map($orden, $v);
        };

        return md5(json_encode($orden($inhoud)));
    }
}
