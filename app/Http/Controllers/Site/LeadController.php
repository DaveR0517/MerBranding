<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\LeadBevestiging;
use App\Mail\NieuweLead;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;

class LeadController extends Controller
{
    /** Versie van de privacytekst waar de aanvrager mee akkoord ging. Placeholder tot de juridische teksten er zijn. */
    public const CONSENT_VERSION = '0.1-concept';

    /** Velden die als kolom worden opgeslagen; al het andere gaat naar data. */
    private const CORE = ['naam', 'email', 'telefoon', 'bedrijfsnaam', 'plaats', 'woonplaats', 'bericht', 'akkoord', 'website', '_token'];

    public function ondernemer(Request $request): RedirectResponse
    {
        $request->validate([
            'naam' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'telefoon' => ['nullable', 'string', 'max:40'],
            'bedrijfsnaam' => ['required', 'string', 'max:190'],
            'plaats' => ['nullable', 'string', 'max:120'],
            'bericht' => ['nullable', 'string', 'max:5000'],
            'akkoord' => ['accepted'],
        ], $this->messages());

        return $this->store($request, Lead::TYPE_ONDERNEMER, 'site.ondernemers');
    }

    public function creator(Request $request): RedirectResponse
    {
        $request->validate([
            'naam' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'telefoon' => ['required', 'string', 'max:40'],
            'woonplaats' => ['required', 'string', 'max:120'],
            'geboortedatum' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'akkoord' => ['accepted'],
        ], $this->messages() + [
            'geboortedatum.before_or_equal' => 'Je moet minimaal 18 jaar zijn om je aan te melden als creator.',
        ]);

        return $this->store($request, Lead::TYPE_CREATOR, 'site.creators');
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

        $lead = Lead::create([
            'type' => $type,
            'name' => $request->string('naam')->trim()->toString(),
            'email' => $request->string('email')->trim()->lower()->toString(),
            'phone' => $request->input('telefoon'),
            'company' => $request->input('bedrijfsnaam'),
            'city' => $request->input('plaats') ?? $request->input('woonplaats'),
            'message' => $request->input('bericht'),
            'data' => $extra ?: null,
            'consent_at' => now(),
            'consent_version' => self::CONSENT_VERSION,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 250),
        ]);

        Mail::to(config('mebranding.team_email'))->queue(new NieuweLead($lead));
        Mail::to($lead->email, $lead->name)->queue(new LeadBevestiging($lead));

        return redirect()->route($backRoute)->with('aangemeld', true);
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
