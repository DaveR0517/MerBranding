<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Uitnodiging voor een nieuw account: een persoonlijke link waarmee iemand zelf een wachtwoord kiest (geldig volgens auth.passwords).
 * Zolang er geen maildienst is, komt de link in beeld bij wie uitnodigt (flash 'uitnodiging'), zodat die hem zelf doorstuurt.
 */
class Uitnodiging
{
    public function __construct(private Melder $melder) {}

    /** @param  array<string, mixed>  $vars */
    public function stuur(User $user, string $sjabloon, array $vars): void
    {
        $link = $this->link($user);
        $this->melder->stuur($sjabloon, $user, $vars, $link);

        if (! self::mailWerkt()) {
            session()->flash('uitnodiging', ['naam' => $user->name, 'email' => $user->email, 'link' => $link]);
        }
    }

    /** Nieuwe link; een eerdere link van deze gebruiker werkt daarna niet meer. */
    public function link(User $user): string
    {
        return route('password.reset', ['token' => Password::broker()->createToken($user), 'email' => $user->email]);
    }

    /** Mail gaat echt de deur uit (niet alleen naar het logbestand). */
    public static function mailWerkt(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }
}
