<?php

namespace App\Services\Gmail;

use App\Models\PostvakAkkoord;
use App\Models\User;

/**
 * Wie ziet welk postvak (besluit Dave, 27 nov): de eigenaren van MEBRANDING (Super Admin) zien alle postvakken;
 * een persoonlijk postvak van een ander pas na akkoord van die persoon (AVG), vastgelegd in postvak_akkoorden.
 * Iedereen ziet zijn eigen postvak. Een postvak zonder portaalaccount (support@) is gedeeld.
 */
class Postvakken
{
    /** @return array<string, ?string> postvak => portaal-e-mailadres van de eigenaar (null: gedeeld) */
    public function alle(): array
    {
        return collect(config('gmail.postvakken'))->map(function (?string $account, string $postvak) {
            $email = $account ?? $postvak;

            return User::where('email', $email)->exists() ? $email : null;
        })->all();
    }

    public function eigenaar(string $postvak): ?User
    {
        $email = $this->alle()[$postvak] ?? null;

        return $email ? User::where('email', $email)->first() : null;
    }

    public function akkoord(string $postvak): ?PostvakAkkoord
    {
        return PostvakAkkoord::where('postvak', $postvak)->whereNull('ingetrokken_op')->first();
    }

    /**
     * Alle postvakken met wat deze gebruiker ermee mag.
     *
     * @return list<array{postvak: string, soort: string, eigenaar: ?string, akkoord_op: ?string, zichtbaar: bool, kan_akkoord: bool}>
     */
    public function voor(User $user): array
    {
        $baas = $user->hasRole('Super Admin');
        $lijst = [];
        foreach ($this->alle() as $postvak => $eigenaarEmail) {
            $eigen = $eigenaarEmail !== null && $eigenaarEmail === strtolower($user->email);
            $akkoord = $eigenaarEmail ? $this->akkoord($postvak) : null;
            $soort = $eigenaarEmail === null ? 'gedeeld' : ($eigen ? 'eigen' : 'persoonlijk');
            $zichtbaar = match ($soort) {
                'eigen' => true,
                'gedeeld' => $baas,
                default => $baas && $akkoord !== null,
            };
            if (! $zichtbaar && ! $baas) {
                continue; // een ander postvak dan het eigen of gedeelde: niet eens noemen
            }
            $lijst[] = [
                'postvak' => $postvak, 'soort' => $soort, 'eigenaar' => $eigenaarEmail ? User::where('email', $eigenaarEmail)->value('name') : null,
                'akkoord_op' => $akkoord?->akkoord_op->toIso8601String(), 'zichtbaar' => $zichtbaar, 'kan_akkoord' => $eigen,
            ];
        }

        return $lijst;
    }

    public function magZien(User $user, string $postvak): bool
    {
        return collect($this->voor($user))->contains(fn ($p) => $p['postvak'] === strtolower($postvak) && $p['zichtbaar']);
    }

    /** Akkoord geven of intrekken kan alleen de eigenaar van het postvak zelf. */
    public function zetAkkoord(User $user, string $postvak, bool $aan, ?string $ip): void
    {
        abort_unless($this->eigenaar($postvak)?->is($user), 403);
        if ($aan) {
            PostvakAkkoord::updateOrCreate(['postvak' => $postvak], ['user_id' => $user->id, 'akkoord_op' => now(), 'ingetrokken_op' => null, 'ip' => $ip]);
        } else {
            PostvakAkkoord::where('postvak', $postvak)->update(['ingetrokken_op' => now()]);
        }
    }
}
