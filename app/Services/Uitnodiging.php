<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * Uitnodiging voor een nieuw account: een persoonlijke link waarmee iemand zelf een wachtwoord kiest (geldig volgens auth.passwords).
 * Zolang er geen maildienst is, komt de link in beeld bij wie uitnodigt (flash 'uitnodiging'), zodat die hem zelf doorstuurt.
 */
class Uitnodiging
{
    /** Dagen voor het verlopen van de link dat g-uitnodiging-verloopt gaat (de tekst zegt "over 2 dagen"). */
    private const DAGEN_VOOR_VERLOPEN = 2;

    public function __construct(private Melder $melder) {}

    /** @param  array<string, mixed>  $vars */
    public function stuur(User $user, string $sjabloon, array $vars): void
    {
        $link = $this->link($user);
        // Inloggegevens: inloggen met dit e-mailadres, wachtwoord kiezen via de link (nooit een wachtwoord in de mail).
        $this->melder->stuur($sjabloon, $user, $vars + ['email' => $user->email], $link);

        // Wie uitnodigde en waarvoor, voor de herinnering g-uitnodiging-verloopt.
        ActivityLog::create([
            'subject_type' => $user->getMorphClass(), 'subject_id' => $user->id, 'event' => 'uitnodiging_verstuurd',
            'data' => array_filter(['sjabloon' => $sjabloon, 'naam' => $vars['naam'] ?? null, 'zaak' => $vars['zaak'] ?? null]),
        ]);

        if (! self::mailWerkt()) {
            session()->flash('uitnodiging', ['naam' => $user->name, 'email' => $user->email, 'link' => $link]);
        }
    }

    /** Nieuwe link; een eerdere link van deze gebruiker werkt daarna niet meer. */
    public function link(User $user): string
    {
        return route('password.reset', ['token' => Password::broker()->createToken($user), 'email' => $user->email]);
    }

    /**
     * g-uitnodiging-verloopt: de link verloopt binnen twee dagen en de genodigde koos nog geen wachtwoord
     * (het e-mailadres is dan nog niet bevestigd). Eén keer per link; alleen als bekend is wie uitnodigde en voor welke zaak.
     */
    public function herinnerVerloopt(): int
    {
        $geldig = (int) config('auth.passwords.users.expire');
        $grens = now()->subMinutes($geldig - self::DAGEN_VOOR_VERLOPEN * 1440);
        $tokens = DB::table(config('auth.passwords.users.table'))
            ->where('created_at', '<=', $grens)->where('created_at', '>', now()->subMinutes($geldig))->get();
        $aantal = 0;

        foreach ($tokens as $token) {
            $user = User::where('email', $token->email)->whereNull('email_verified_at')->first();
            $context = $user ? ActivityLog::where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)
                ->where('event', 'uitnodiging_verstuurd')->latest('id')->first()?->data : null;
            if (! isset($context['naam'], $context['zaak'])) {
                continue;
            }
            if (! $user->markeerHerinnering('uitnodiging-verloopt-'.Carbon::parse($token->created_at)->timestamp)) {
                continue;
            }
            $this->melder->stuur('g-uitnodiging-verloopt', $user, ['naam' => $context['naam'], 'zaak' => $context['zaak']], route('login'));
            $aantal++;
        }

        return $aantal;
    }

    /** Mail gaat echt de deur uit (niet alleen naar het logbestand). */
    public static function mailWerkt(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }
}
