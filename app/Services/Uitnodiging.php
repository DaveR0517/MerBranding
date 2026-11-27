<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Creator;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * Uitnodiging voor een account: een persoonlijke link waarmee iemand zelf een wachtwoord kiest (geldig volgens auth.passwords).
 * Alleen een account dat nog niet in gebruik is (User::inGebruik) krijgt zo'n link. Een account in gebruik krijgt
 * g-toegevoegd met een knop naar het inlogscherm: wie een bestaand adres uitnodigt, kan zo nooit een wachtwoord voor
 * dat account laten kiezen en er zo in komen.
 * Zolang er geen maildienst is, komt de link in beeld bij wie uitnodigt (flash 'uitnodiging'), zodat die hem zelf doorstuurt.
 * Dat mag alleen voor een account dat in deze aanvraag nieuw is aangemaakt, of als het team uitnodigt (opnieuw uitnodigen
 * bij screening of bedrijfsbeheer). Anders zou bijvoorbeeld een ondernemer de link van andermans ongebruikte account zien.
 */
class Uitnodiging
{
    /** Dagen voor het verlopen van de link dat g-uitnodiging-verloopt gaat (de tekst zegt "over 2 dagen"). */
    private const DAGEN_VOOR_VERLOPEN = 2;

    public function __construct(private Melder $melder) {}

    /**
     * @param  array<string, mixed>  $vars  variabelen voor $sjabloon, altijd met zaak en rol (waartoe het account toegang krijgt, ook voor g-toegevoegd)
     * @return bool true als er een wachtwoordlink is verstuurd; false als het account al in gebruik was (dan alleen g-toegevoegd)
     */
    public function stuur(User $user, string $sjabloon, array $vars): bool
    {
        if ($user->inGebruik()) {
            $this->melder->stuur('g-toegevoegd', $user, ['zaak' => $vars['zaak'], 'rol' => $vars['rol'], 'email' => $user->email], route('login'));

            return false;
        }

        $link = $this->link($user);
        // Inloggegevens: inloggen met dit e-mailadres, wachtwoord kiezen via de link (nooit een wachtwoord in de mail).
        $this->melder->stuur($sjabloon, $user, $vars + ['email' => $user->email], $link);

        // Wie uitnodigde en waarvoor, voor de herinnering g-uitnodiging-verloopt.
        ActivityLog::create([
            'subject_type' => $user->getMorphClass(), 'subject_id' => $user->id, 'event' => 'uitnodiging_verstuurd',
            'data' => array_filter(['sjabloon' => $sjabloon, 'naam' => $vars['naam'] ?? null, 'zaak' => $vars['zaak'] ?? null]),
        ]);

        if (! self::mailWerkt() && ($user->wasRecentlyCreated || auth()->user()?->isTeam())) {
            session()->flash('uitnodiging', ['naam' => $user->name, 'email' => $user->email, 'link' => $link]);
        }

        return true;
    }

    /** Variabelen voor c-welkom, met zaak en rol voor wie al een account had (g-toegevoegd). */
    public static function creatorVars(Creator $creator): array
    {
        return ['niveau' => $creator->levelName(), 'zaak' => 'MEBRANDING', 'rol' => 'creator op niveau '.$creator->levelName()];
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
