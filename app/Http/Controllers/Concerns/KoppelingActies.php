<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use App\Models\Creator;
use App\Services\Koppelingen\Koppelingen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Koppelingenscherm van creator en ondernemer: toestemming, koppelen (of handmatig), verbreken.
 * De controller levert de eigenaar (creator of bedrijf) en de omgeving.
 */
trait KoppelingActies
{
    abstract protected function eigenaar(Request $request, bool $beheren = false): Creator|Company;

    abstract protected function omgeving(): string;

    public function koppelingen(Request $request, Koppelingen $k): Response
    {
        $eigenaar = $this->eigenaar($request);

        return Inertia::render("{$this->omgeving()}/content/koppelingen", [
            'accounts' => $k->overzicht($eigenaar),
            'toestemming' => $k->toestemmingstekst($this->omgeving()),
            'mag_beheren' => $this->magBeheren($request),
        ]);
    }

    /** Stap 1: toestemming geven en loggen. Daarna inloggen bij het platform, of handmatig zolang de sleutels er niet zijn. */
    public function toestemming(Request $request, string $platform, Koppelingen $k): HttpResponse
    {
        $request->validate(['akkoord' => ['accepted']], ['akkoord.accepted' => 'Vink aan dat je toestemming geeft.']);
        $k->toestemming($this->eigenaar($request, true), $platform, $request->user(), $request->ip());

        $state = Str::random(40);
        $request->session()->put("koppeling.{$platform}", $state);
        $resultaat = $k->platform($platform)->autorisatieUrl(route("{$this->omgeving()}.content.koppelingen.terug", $platform), $state);

        if ($resultaat->isGelukt()) {
            return Inertia::location($resultaat->data['url']);
        }

        return back()->with('melding', $resultaat->melding);
    }

    /** Stap 2 zonder sleutels: gebruikersnaam invullen en professioneel account bevestigen. */
    public function handmatig(Request $request, string $platform, Koppelingen $k): RedirectResponse
    {
        $data = $request->validate([
            'handle' => ['required', 'string', 'max:60', 'regex:/^@?[A-Za-z0-9._]{1,30}$/'],
            'professioneel' => ['accepted'],
        ], [
            'handle.regex' => 'Vul je gebruikersnaam in, bijvoorbeeld @lunchroombloesem.',
            'professioneel.accepted' => 'Bevestig dat het een professioneel of creator-account is. Dat is nodig voor de cijfers.',
        ]);

        $k->handmatig($this->eigenaar($request, true), $platform, $data['handle'], $request->user());

        return back()->with('melding', 'Je account staat erin. Tot de koppeling er is, leveren we de cijfers handmatig aan.');
    }

    public function verbreken(Request $request, string $platform, Koppelingen $k): RedirectResponse
    {
        $account = $k->account($this->eigenaar($request, true), $platform);
        abort_unless($account->exists, 404);
        $k->verbreken($account, $request->user());

        return back()->with('melding', 'De koppeling is verbroken en je toestemming is ingetrokken.');
    }

    /** Terugkomst van het platform na inloggen. Werkt pas echt met sleutels (fase 3). */
    public function terug(Request $request, string $platform, Koppelingen $k): RedirectResponse
    {
        $verwacht = $request->session()->pull("koppeling.{$platform}");
        $naar = to_route("{$this->omgeving()}.content.koppelingen");

        if (! $verwacht || ! hash_equals($verwacht, (string) $request->query('state')) || ! $request->filled('code')) {
            return $naar->with('melding', 'Koppelen is niet gelukt. Probeer het opnieuw.');
        }

        $account = $k->account($this->eigenaar($request, true), $platform);
        $resultaat = $k->platform($platform)->wisselCodeIn((string) $request->query('code'), route("{$this->omgeving()}.content.koppelingen.terug", $platform));

        if (! $resultaat->isGelukt() || ! $account->exists) {
            return $naar->with('melding', $resultaat->melding);
        }

        $k->tokensOpslaan($account, $resultaat, $request->user());

        return $naar->with('melding', "{$k->platform($platform)->naam()} is gekoppeld.");
    }

    protected function magBeheren(Request $request): bool
    {
        return true;
    }
}
