<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Creator;
use App\Models\CreatorProfiel;
use App\Models\Payout;
use App\Models\Signature;
use App\Models\Status;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Module creator-profiel: profiel, uitbetaalgegevens, raamovereenkomst, vergoedingen en agenda van de creator zelf. */
class CreatorProfielController extends Controller
{
    /* ------------------------------------------------------------------ Start: checklist en raamovereenkomst */

    public function start(Request $request): Response
    {
        $creator = $this->creator($request);
        $handtekening = CreatorProfiel::raamGetekend($request->user());

        return Inertia::render('creator/profiel/start', [
            'name' => $request->user()->name,
            'level' => $creator->levelName(),
            'checklist' => $this->checklist($creator, $request),
            'agreement' => [
                'version' => CreatorProfiel::raamVersie(),
                'signed_at' => $handtekening?->created_at?->toIso8601String(),
                'signed_name' => $handtekening?->signed_name,
                'text' => $this->raamTekst(),
            ],
        ]);
    }

    public function tekenen(Request $request): RedirectResponse
    {
        $this->creator($request);
        $data = $request->validate([
            'signed_name' => ['required', 'string', 'max:120'],
            'akkoord' => ['accepted'],
        ], ['akkoord.accepted' => 'Ga akkoord met de raamovereenkomst.', 'signed_name.required' => 'Typ je volledige naam als handtekening.']);

        if (! CreatorProfiel::raamGetekend($request->user())) {
            $handtekening = Signature::create([
                'user_id' => $request->user()->id, 'agreement' => CreatorProfiel::RAAMOVEREENKOMST,
                'version' => CreatorProfiel::raamVersie(), 'signed_name' => $data['signed_name'], 'ip' => $request->ip(),
            ]);
            ActivityLog::create([
                'user_id' => $request->user()->id, 'subject_type' => $handtekening->getMorphClass(), 'subject_id' => $handtekening->id,
                'event' => 'raamovereenkomst_getekend', 'data' => ['versie' => $handtekening->version],
            ]);
        }

        return to_route('creator.profiel.start')->with('melding', 'Raamovereenkomst getekend. Dank je wel.');
    }

    /* ------------------------------------------------------------------ Profiel */

    public function profiel(Request $request): Response
    {
        $creator = $this->creator($request);
        $profiel = CreatorProfiel::van($creator);

        return Inertia::render('creator/profiel/profiel', [
            'profile' => [
                'name' => $request->user()->name,
                'level' => $creator->levelName(),
                'photo_url' => $profiel->fotoUrl(),
                'bio' => $profiel->bio,
                'kind' => $creator->kind,
                'niches' => $creator->niches ?? [],
                'city' => $creator->city,
                'radius_km' => $profiel->radius_km,
                'instagram' => $creator->instagram,
                'tiktok' => $creator->tiktok,
                'portfolio_links' => $profiel->portfolio_links ?? [],
            ],
            'options' => [
                'niches' => array_values(CreatorProfiel::NICHES),
                'kinds' => collect(CreatorProfiel::SOORTEN)->map(fn ($n, $k) => ['value' => $k, 'name' => $n])->values(),
            ],
            'agreement_signed' => (bool) CreatorProfiel::raamGetekend($request->user()),
        ]);
    }

    public function profielOpslaan(Request $request): RedirectResponse
    {
        $creator = $this->creator($request);
        $data = $request->validate([
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photo' => ['boolean'],
            'bio' => ['nullable', 'string', 'max:600'],
            'kind' => ['required', Rule::in(array_keys(CreatorProfiel::SOORTEN))],
            'niches' => ['array', 'max:7'],
            'niches.*' => [Rule::in(array_values(CreatorProfiel::NICHES))],
            'city' => ['required', 'string', 'max:120'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:300'],
            'instagram' => ['nullable', 'string', 'max:60', 'regex:/^@?[A-Za-z0-9._]+$/'],
            'tiktok' => ['nullable', 'string', 'max:60', 'regex:/^@?[A-Za-z0-9._]+$/'],
            'portfolio_links' => ['array', 'max:5'],
            'portfolio_links.*' => ['nullable', 'url:https,http', 'max:255'],
        ], [
            'photo.image' => 'Kies een foto (jpg, png of webp).', 'photo.max' => 'De foto mag maximaal 5 MB zijn.',
            'city.required' => 'Vul je woonplaats in.', 'instagram.regex' => 'Alleen je gebruikersnaam, zonder link.',
            'tiktok.regex' => 'Alleen je gebruikersnaam, zonder link.', 'portfolio_links.*.url' => 'Vul een volledige link in, met https://.',
        ]);

        $profiel = CreatorProfiel::van($creator);
        if ($request->hasFile('photo')) {
            $oud = $profiel->photo_path;
            $profiel->photo_path = $request->file('photo')->store("creators/{$creator->id}", 'public');
            if ($oud) {
                Storage::disk('public')->delete($oud);
            }
        } elseif ($request->boolean('remove_photo') && $profiel->photo_path) {
            Storage::disk('public')->delete($profiel->photo_path);
            $profiel->photo_path = null;
        }

        $profiel->fill([
            'bio' => $data['bio'] ?? null,
            'radius_km' => $data['radius_km'] ?? null,
            'portfolio_links' => array_values(array_filter($data['portfolio_links'] ?? [])),
        ])->save();

        $handle = fn (?string $h) => filled($h) ? '@'.ltrim(trim($h), '@') : null;
        $creator->update([
            'kind' => $data['kind'], 'niches' => array_values($data['niches'] ?? []), 'city' => $data['city'],
            'instagram' => $handle($data['instagram'] ?? null), 'tiktok' => $handle($data['tiktok'] ?? null),
        ]);

        return back()->with('melding', 'Je profiel is opgeslagen.');
    }

    /* ------------------------------------------------------------------ Account: uitbetaalgegevens en btw */

    public function account(Request $request): Response
    {
        $creator = $this->creator($request);
        $gegevens = CreatorProfiel::uitbetaling($creator);
        $handtekening = CreatorProfiel::raamGetekend($request->user());

        return Inertia::render('creator/profiel/account', [
            'email' => $request->user()->email,
            'payout' => [
                'complete' => CreatorProfiel::uitbetalingCompleet($creator),
                'iban_masked' => CreatorProfiel::maskeer($gegevens['iban'] ?? null),
                'name' => $gegevens['name'] ?? null,
                'vat_status' => $creator->vat_status,
                'vat_number' => $gegevens['vat_number'] ?? null,
            ],
            'vat_options' => collect(CreatorProfiel::BTW)->map(fn ($n, $k) => ['value' => $k, 'name' => $n])->values(),
            'agreement' => ['version' => CreatorProfiel::raamVersie(), 'signed_at' => $handtekening?->created_at?->toIso8601String()],
        ]);
    }

    public function accountOpslaan(Request $request): RedirectResponse
    {
        $creator = $this->creator($request);
        $bestaand = CreatorProfiel::uitbetaling($creator);
        $data = $request->validate([
            // Leeg laten houdt het bewaarde IBAN (we tonen het nummer maar half).
            'iban' => [filled($bestaand['iban'] ?? null) ? 'nullable' : 'required', 'string', 'max:42', function ($attr, $waarde, $fail) {
                if (filled($waarde) && ! CreatorProfiel::ibanGeldig($waarde)) {
                    $fail('Dit IBAN klopt niet. Controleer de cijfers.');
                }
            }],
            'name' => ['required', 'string', 'max:120'],
            'vat_status' => ['required', Rule::in(array_keys(CreatorProfiel::BTW))],
            'vat_number' => ['nullable', 'required_if:vat_status,btw', 'string', 'max:20', 'regex:/^[A-Za-z]{2}[A-Za-z0-9]{8,14}$/'],
        ], [
            'iban.required' => 'Vul je IBAN in.', 'name.required' => 'Vul de naam van de rekeninghouder in.',
            'vat_status.required' => 'Kies je btw-status.', 'vat_number.required_if' => 'Vul je btw-nummer in.',
            'vat_number.regex' => 'Een btw-nummer ziet eruit als NL123456789B01.',
        ]);

        $iban = filled($data['iban'] ?? null) ? strtoupper(preg_replace('/\s+/', '', $data['iban'])) : $bestaand['iban'];
        $creator->update([
            'payout_details' => json_encode(array_filter([
                'iban' => $iban, 'name' => $data['name'],
                'vat_number' => $data['vat_status'] === 'btw' ? strtoupper($data['vat_number']) : null,
            ])),
            'vat_status' => $data['vat_status'],
        ]);
        ActivityLog::create([
            'user_id' => $request->user()->id, 'subject_type' => $creator->getMorphClass(), 'subject_id' => $creator->id,
            'event' => 'uitbetaalgegevens_gewijzigd', 'data' => ['btw' => $data['vat_status']],
        ]);

        return back()->with('melding', 'Je uitbetaalgegevens zijn opgeslagen.');
    }

    /* ------------------------------------------------------------------ Vergoedingen */

    public function vergoedingen(Request $request): Response
    {
        $creator = $this->creator($request);
        $uitbetalingen = Payout::with('collaboration.company')->where('creator_id', $creator->id)->latest('run_on')->get();
        $wachtend = Collaboration::with('company')->where('creator_id', $creator->id)->where('fee_cents', '>', 0)
            ->whereNotIn('status', [Status::Live->value, Status::Afgerond->value, Status::Geannuleerd->value, Status::NietVerschenen->value])
            ->whereNotIn('id', $uitbetalingen->pluck('collaboration_id'))->get();
        $gegevens = CreatorProfiel::uitbetaling($creator);

        return Inertia::render('creator/profiel/vergoedingen', [
            'level' => $creator->levelName(),
            'paid_level' => $creator->feeCents() > 0,
            'fee_cents' => $creator->feeCents(),
            'next_run' => $this->volgendeVrijdag()->toDateString(),
            'totals' => [
                'open_cents' => (int) $uitbetalingen->where('status', '!=', 'uitbetaald')->sum('amount_cents'),
                'paid_year_cents' => (int) $uitbetalingen->where('status', 'uitbetaald')->filter(fn ($p) => $p->run_on->isCurrentYear())->sum('amount_cents'),
                'waiting_cents' => (int) $wachtend->sum('fee_cents'),
            ],
            'payouts' => $uitbetalingen->map(fn (Payout $p) => [
                'id' => $p->id, 'company' => $p->collaboration->company->name, 'title' => $p->collaboration->title,
                'amount_cents' => $p->amount_cents, 'status' => $p->status, 'run_on' => $p->run_on->toDateString(),
                'live_at' => $p->collaboration->live_at?->toIso8601String(),
            ])->values(),
            'waiting' => $wachtend->map(fn (Collaboration $c) => [
                'id' => $c->id, 'company' => $c->company->name, 'title' => $c->title, 'amount_cents' => $c->fee_cents, 'status' => $c->status->label(),
            ])->values(),
            'payout_details' => [
                'complete' => CreatorProfiel::uitbetalingCompleet($creator),
                'iban_masked' => CreatorProfiel::maskeer($gegevens['iban'] ?? null),
                'vat_status' => $creator->vat_status ? CreatorProfiel::BTW[$creator->vat_status] ?? $creator->vat_status : null,
            ],
        ]);
    }

    /* ------------------------------------------------------------------ Agenda */

    public function agenda(Request $request): Response
    {
        $creator = $this->creator($request);
        $items = Collaboration::with(['company', 'location'])->where('creator_id', $creator->id)
            ->whereNotIn('status', [Status::Geannuleerd->value])->get()
            ->flatMap(fn (Collaboration $c) => $this->afspraken($c))
            ->sortBy('at')->values();
        $vandaag = today()->startOfDay();

        return Inertia::render('creator/profiel/agenda', [
            'upcoming' => $items->filter(fn ($i) => $i['moment'] >= $vandaag)->map(fn ($i) => collect($i)->except('moment'))->values(),
            'past' => $items->filter(fn ($i) => $i['moment'] < $vandaag && $i['kind'] === 'bezoek')->reverse()->take(20)->map(fn ($i) => collect($i)->except('moment'))->values(),
        ]);
    }

    public function ics(Request $request, Collaboration $samenwerking, string $soort): \Illuminate\Http\Response
    {
        $creator = $this->creator($request);
        abort_unless($samenwerking->creator_id === $creator->id, 403);
        $afspraak = collect($this->afspraken($samenwerking->loadMissing(['company', 'location'])))->firstWhere('kind', $soort);
        abort_unless($afspraak, 404);

        $esc = fn (string $t) => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $t);
        $utc = fn (CarbonInterface $d) => $d->copy()->utc()->format('Ymd\THis\Z');
        $moment = $afspraak['moment'];
        $regels = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//MEBRANDING//Creatorportaal//NL', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$samenwerking->number.'-'.$soort.'@'.config('mebranding.domains.app'),
            'DTSTAMP:'.$utc(now()),
            ...($soort === 'publicatie'
                ? ['DTSTART;VALUE=DATE:'.$moment->format('Ymd'), 'DTEND;VALUE=DATE:'.$moment->copy()->addDay()->format('Ymd')]
                : ['DTSTART:'.$utc($moment), 'DTEND:'.$utc($moment->copy()->addMinutes($soort === 'bezoek' ? 90 : 30))]),
            'SUMMARY:'.$esc($afspraak['label'].': '.$samenwerking->company->name),
            'LOCATION:'.$esc(trim(($samenwerking->location->address ? $samenwerking->location->address.', ' : '').$samenwerking->location->city)),
            'DESCRIPTION:'.$esc($samenwerking->title.' ('.$samenwerking->number.'). '.route('creator.klus', $samenwerking)),
            'URL:'.route('creator.klus', $samenwerking),
            'END:VEVENT', 'END:VCALENDAR',
        ];

        return response(implode("\r\n", $regels)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($samenwerking->company->name.' '.$soort).'.ics"',
        ]);
    }

    /* ------------------------------------------------------------------ Hulpjes */

    /** @return list<array<string, mixed>> */
    private function afspraken(Collaboration $c): array
    {
        $basis = [
            'collaboration_id' => $c->id, 'company' => $c->company->name, 'title' => $c->title, 'status' => $c->status->label(),
            'address' => trim(($c->location->address ? $c->location->address.', ' : '').$c->location->city),
        ];
        $lijst = [];
        if ($c->visit_at) {
            // VOORSTEL: een bezoek duurt 90 minuten in de agenda.
            $lijst[] = ['kind' => 'bezoek', 'label' => 'Bezoek', 'moment' => $c->visit_at, 'at' => $c->visit_at->toIso8601String(), 'end' => $c->visit_at->copy()->addMinutes(90)->toIso8601String(), 'all_day' => false];
        }
        if ($c->content_deadline_at && ! in_array($c->status, [Status::Goedgekeurd, Status::Live, Status::Afgerond], true)) {
            $lijst[] = ['kind' => 'deadline', 'label' => 'Deadline video', 'moment' => $c->content_deadline_at, 'at' => $c->content_deadline_at->toIso8601String(), 'end' => null, 'all_day' => false];
        }
        if ($c->publish_on) {
            $lijst[] = ['kind' => 'publicatie', 'label' => 'Publiceren', 'moment' => $c->publish_on->startOfDay(), 'at' => $c->publish_on->toDateString(), 'end' => null, 'all_day' => true];
        }

        return array_map(fn ($i) => $basis + $i, $lijst);
    }

    /** @return list<array{key: string, label: string, done: bool, href: string}> */
    private function checklist(Creator $creator, Request $request): array
    {
        $profiel = CreatorProfiel::van($creator);

        return [
            ['key' => 'profiel', 'label' => 'Profiel compleet: bio, woonplaats en niches', 'done' => filled($profiel->bio) && filled($creator->city) && ! empty($creator->niches), 'href' => route('creator.profiel.index')],
            ['key' => 'foto', 'label' => 'Profielfoto', 'done' => filled($profiel->photo_path), 'href' => route('creator.profiel.index')],
            ['key' => 'portfolio', 'label' => 'Portfolio: minimaal één link naar je werk', 'done' => ! empty($profiel->portfolio_links), 'href' => route('creator.profiel.index')],
            ['key' => 'kanalen', 'label' => 'Instagram of TikTok opgegeven', 'done' => filled($creator->instagram) || filled($creator->tiktok), 'href' => route('creator.profiel.index')],
            ['key' => 'raamovereenkomst', 'label' => 'Raamovereenkomst getekend', 'done' => (bool) CreatorProfiel::raamGetekend($request->user()), 'href' => route('creator.profiel.start').'#raamovereenkomst'],
            ['key' => 'uitbetaling', 'label' => 'Uitbetaalgegevens en btw-status (voor je eerste uitbetaling)', 'done' => CreatorProfiel::uitbetalingCompleet($creator), 'href' => route('creator.profiel.account')],
        ];
    }

    /** @return list<string> */
    private function raamTekst(): array
    {
        $v = CreatorProfiel::raamVersie();

        return [
            "Concept {$v}. Dit is een placeholder: de definitieve creatorovereenkomst volgt (open punt 11).",
            'Je werkt als zelfstandige creator samen met MEBRANDING. Per samenwerking teken je daarnaast een eigen contract met de afspraken over het bezoek, de video en de publicatie.',
            'Je maakt reclame altijd herkenbaar: het platformlabel "Betaalde samenwerking", de vermelding aan het begin van je bijschrift en tekst in beeld.',
            'Bij Bronze, Silver en Gold krijg je een gratis bezoek. Vanaf Platinum krijg je ook een vergoeding in geld, uitbetaald in de betaalrun op vrijdag.',
            'Btw en KOR zijn je eigen verantwoordelijkheid. MEBRANDING rekent niet voor je en neemt je opgave over op de specificatie.',
            'Weigeren van een uitnodiging heeft geen gevolg voor je score. Besluiten over pauzeren of blokkeren neemt altijd een mens, met reden.',
        ];
    }

    private function volgendeVrijdag(): CarbonInterface
    {
        return today()->isFriday() ? today() : today()->next('Friday');
    }

    private function creator(Request $request): Creator
    {
        return $request->user()->creator()->firstOrFail();
    }
}
