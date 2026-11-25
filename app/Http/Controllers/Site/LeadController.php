<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\NieuweLead;
use App\Models\Lead;
use App\Models\Uitnodigingslink;
use App\Services\Melder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    /** Versie van de privacytekst waar de aanvrager mee akkoord ging. Placeholder tot de juridische teksten er zijn. */
    public const CONSENT_VERSION = '0.1-concept';

    /** Velden die als kolom worden opgeslagen (of omgezet); al het andere gaat naar data. */
    private const CORE = ['naam', 'voornaam', 'achternaam', 'email', 'telefoon', 'bedrijfsnaam', 'woonplaats', 'bericht', 'niche', 'akkoord', 'website', '_token'];

    /** Keuzes in het formulier Kennismaken. */
    public const BRANCHES = ['Restaurant of lunchroom', 'Hotel of B&B', 'Museum of uitje', 'Winkel', 'Anders'];

    public const VESTIGINGEN = ['1', '2 tot 3', '4 of meer'];

    /** Keuze "Wat voor creator ben je?": waarde => [tekst, niches uit CreatorProfiel::NICHES]. */
    public const CREATORSOORTEN = [
        'food-lifestyle' => ['Food en lifestyle', ['eten', 'lifestyle']],
        'reizen' => ['Reizen en hotels', ['reizen']],
        'uitjes' => ['Uitjes en cultuur', ['uitjes']],
        'mode' => ['Mode en winkels', ['mode']],
        'anders' => ['Anders', ['anders']],
    ];

    public function ondernemer(Request $request): RedirectResponse
    {
        $request->validate([
            'naam' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'telefoon' => ['nullable', 'string', 'max:40'],
            'bedrijfsnaam' => ['required', 'string', 'max:190'],
            'woonplaats' => ['required', 'string', 'max:120'], // plaats van de zaak: voor de kaart van het team
            'adres' => ['nullable', 'string', 'max:190'],
            'branche' => ['nullable', Rule::in(self::BRANCHES)],
            'vestigingen' => ['nullable', Rule::in(self::VESTIGINGEN)],
            'pakket' => ['nullable', Rule::in(['lokaal', 'groei', 'regio'])],
            'bericht' => ['nullable', 'string', 'max:5000'],
            'akkoord' => ['accepted'],
        ], $this->messages());

        return $this->store($request, Lead::TYPE_ONDERNEMER, 'site.kennismaken');
    }

    public function creator(Request $request): RedirectResponse
    {
        $request->validate([
            'voornaam' => ['required', 'string', 'max:60'],
            'achternaam' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:190'],
            'geboortedatum' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'instagram-naam' => ['nullable', 'string', 'max:60'],
            'tiktok-naam' => ['nullable', 'string', 'max:60'],
            'niche' => ['nullable', Rule::in(array_keys(self::CREATORSOORTEN))],
            'woonplaats' => ['required', 'string', 'max:120'],
            'bericht' => ['nullable', 'string', 'max:5000'],
            'akkoord' => ['accepted'],
        ], $this->messages() + [
            'geboortedatum.before_or_equal' => 'Je moet minimaal 18 jaar zijn om je aan te melden als creator.',
        ]);

        $request->merge([
            'naam' => trim($request->string('voornaam')->trim().' '.$request->string('achternaam')->trim()),
            'niches' => self::CREATORSOORTEN[$request->input('niche')][1] ?? null,
        ]);

        return $this->store($request, Lead::TYPE_CREATOR, 'site.aanmelden-creator');
    }

    private function store(Request $request, string $type, string $backRoute): RedirectResponse
    {
        // Honeypot: echte bezoekers zien het veld "website" niet. Bots krijgen een gewone bedankpagina.
        if (filled($request->input('website'))) {
            return redirect()->route($backRoute)->with('aangemeld', true);
        }

        $extra = collect(Arr::except($request->all(), self::CORE))
            ->filter(fn ($v) => is_scalar($v) || is_array($v))
            ->map(fn ($v) => is_string($v) ? mb_substr($v, 0, 2000) : $v)
            ->all();

        // Vriend uitnodigen: de code komt alleen uit de cookie van de uitnodigingslink, nooit uit een formulierveld.
        unset($extra['referral_code']);
        $code = $request->cookie(Uitnodigingslink::COOKIE);
        if ($code && Uitnodigingslink::where('code', $code)->exists()) {
            $extra['referral_code'] = $code;
        }

        $lead = Lead::create([
            'type' => $type,
            'name' => $request->string('naam')->trim()->toString(),
            'email' => $request->string('email')->trim()->lower()->toString(),
            'phone' => $request->input('telefoon'),
            'company' => $request->input('bedrijfsnaam'),
            'city' => $request->input('woonplaats'),
            'message' => $request->input('bericht'),
            'data' => $extra ?: null,
            'consent_at' => now(),
            'consent_version' => self::CONSENT_VERSION,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 250),
        ]);

        Mail::to(config('mebranding.team_email'))->queue(new NieuweLead($lead));
        app(Melder::class)->stuurNaarAdres($lead->isOndernemer() ? 'o-kennismaking' : 'c-aanmelding', $lead->email, $lead->name, [
            'zaak' => $lead->company ?: $lead->name,
            'plaats' => $lead->city ?? '',
        ], 'https://'.config('mebranding.domains.site'));

        return redirect()->route($backRoute)->with('aangemeld', true)->withoutCookie(Uitnodigingslink::COOKIE);
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'required' => 'Vul dit veld in.',
            'email' => 'Vul een geldig e-mailadres in.',
            'accepted' => 'Ga akkoord met de privacyverklaring om verder te gaan.',
            'max' => 'Dit veld is te lang.',
            'date' => 'Vul een geldige datum in.',
        ];
    }
}
