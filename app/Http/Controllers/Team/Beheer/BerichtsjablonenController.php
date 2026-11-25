<?php

namespace App\Http\Controllers\Team\Beheer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MessageTemplate;
use App\Notifications\Testbericht;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Berichtsjablonen (CLAUDE.md 8, zelfbeheer): teksten en kanalen per melding aanpassen zonder ontwikkelaar.
 * Een verplicht kanaal (beveiliging, geld, juridisch, voortgang) gaat niet uit; een kanaal dat niet bestaat ("nee") gaat niet aan.
 * Voorbeeldwaarden en beschrijving komen uit database/data/berichtsjablonen.json.
 */
class BerichtsjablonenController extends Controller
{
    private const KANALEN = ['inapp', 'email', 'push'];

    /** Keuzes voor een kanaal dat niet vastligt. */
    private const KEUZES = ['aan', 'uit', 'vangnet', 'samenvatting'];

    public function index(): Response
    {
        Gate::authorize('instellingen.beheren');
        $bron = $this->bron();

        return Inertia::render('team/beheer/berichtsjablonen', [
            'rollen' => $bron['meta']['rollen'] ?? [],
            'soorten' => $bron['meta']['soorten'] ?? [],
            'spelregels' => $bron['meta']['spelregels'] ?? [],
            'sjablonen' => MessageTemplate::orderBy('id')->get()->map(function (MessageTemplate $t) use ($bron) {
                $s = $bron['sjablonen'][$t->key] ?? [];

                return [
                    'id' => $t->id, 'key' => $t->key, 'role' => $t->role, 'event' => $t->event,
                    'soort' => $s['soort'] ?? null, 'ontvanger' => $s['ontvanger'] ?? null, 'moment' => $s['moment'] ?? null,
                    'voorstel' => ($s['status'] ?? 'Voorstel') !== 'FO',
                    'channels' => $t->channels, 'vast' => collect(self::KANALEN)->mapWithKeys(fn ($k) => [$k => $this->vast($t, $k)]),
                    'subject' => $t->subject, 'inapp' => $t->inapp,
                    'email' => ['aanhef' => $t->email['aanhef'] ?? '', 'alineas' => $t->email['alineas'] ?? [], 'knop' => $t->email['knop'] ?? ''],
                    'voorbeeld' => $s['voorbeeld'] ?? [],
                    'updated_at' => $t->updated_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    public function opslaan(Request $request, MessageTemplate $sjabloon): RedirectResponse
    {
        Gate::authorize('instellingen.beheren');
        $data = $this->valideer($request, $sjabloon);

        $oud = $sjabloon->only(['subject', 'inapp', 'email', 'channels']);
        $sjabloon->update($data);
        $nieuw = $sjabloon->only(['subject', 'inapp', 'email', 'channels']);
        $gewijzigd = array_keys(array_filter($nieuw, fn ($v, $k) => $v !== $oud[$k], ARRAY_FILTER_USE_BOTH));

        if (! $gewijzigd) {
            return back()->with('melding', 'Er is niets veranderd.');
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'event' => 'berichtsjabloon_gewijzigd',
            'reason' => Str::limit("{$sjabloon->key}: {$sjabloon->event}", 250),
            'data' => ['key' => $sjabloon->key, 'oud' => array_intersect_key($oud, array_flip($gewijzigd)), 'nieuw' => array_intersect_key($nieuw, array_flip($gewijzigd))],
        ]);

        return back()->with('melding', "Sjabloon {$sjabloon->key} opgeslagen.");
    }

    /** Stuurt de e-mail zoals hij nu in het formulier staat naar jezelf, met de voorbeeldwaarden. Slaat niets op. */
    public function testmail(Request $request, MessageTemplate $sjabloon): RedirectResponse
    {
        Gate::authorize('instellingen.beheren');
        $concept = (new MessageTemplate)->forceFill($sjabloon->only(['key', 'role', 'event']) + $this->valideer($request, $sjabloon));
        $voorbeeld = $this->bron()['sjablonen'][$sjabloon->key]['voorbeeld'] ?? [];

        $request->user()->notifyNow(new Testbericht($concept, array_map('strval', $voorbeeld) + ['voornaam' => Str::before($request->user()->name, ' ')], route('start')));

        return back()->with('melding', "Testmail verstuurd naar {$request->user()->email}.");
    }

    /** @return array<string, mixed> */
    private function valideer(Request $request, MessageTemplate $sjabloon): array
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:190'],
            'inapp' => ['nullable', 'string', 'max:1000'],
            'email.aanhef' => ['nullable', 'string', 'max:190'],
            'email.alineas' => ['array', 'max:8'],
            'email.alineas.*' => ['nullable', 'string', 'max:1000'],
            'email.knop' => ['nullable', 'string', 'max:60'],
            'channels' => ['required', 'array'],
            'channels.*' => ['string'],
        ], ['subject.required' => 'Een onderwerp is verplicht.']);

        $kanalen = [];
        foreach (self::KANALEN as $k) {
            $huidig = $sjabloon->channels[$k] ?? 'nee';
            $gekozen = $data['channels'][$k] ?? $huidig;

            if ($this->vast($sjabloon, $k)) {
                if ($gekozen !== $huidig) {
                    throw ValidationException::withMessages(["channels.{$k}" => $huidig === 'nee'
                        ? 'Dit kanaal bestaat niet voor deze melding.'
                        : 'Dit kanaal is verplicht voor deze melding en kan niet uit.']);
                }
            } elseif (! in_array($gekozen, self::KEUZES, true)) {
                throw ValidationException::withMessages(["channels.{$k}" => 'Kies aan, uit, alleen zonder push of samenvatting.']);
            }
            $kanalen[$k] = $gekozen;
        }

        return [
            'subject' => $data['subject'],
            'inapp' => $data['inapp'] ?? null,
            'email' => [
                ...($sjabloon->email ?? []),
                'aanhef' => $data['email']['aanhef'] ?? '',
                'alineas' => array_values(array_filter($data['email']['alineas'] ?? [], fn ($a) => filled($a))),
                'knop' => $data['email']['knop'] ?? '',
            ],
            'channels' => $kanalen,
        ];
    }

    /** Ligt dit kanaal vast? Verplicht (in de database of in het bronbestand) of niet beschikbaar. */
    private function vast(MessageTemplate $t, string $kanaal): bool
    {
        $bron = $this->bron()['sjablonen'][$t->key]['kanalen'][$kanaal] ?? null;

        return in_array($t->channels[$kanaal] ?? 'nee', ['verplicht', 'nee'], true) || $bron === 'verplicht';
    }

    /** @return array{meta: array<string, mixed>, sjablonen: array<string, array<string, mixed>>} */
    private function bron(): array
    {
        return once(function () {
            $data = json_decode((string) file_get_contents(database_path('data/berichtsjablonen.json')), true) ?: [];

            return ['meta' => $data['meta'] ?? [], 'sjablonen' => collect($data['sjablonen'] ?? [])->keyBy('id')->all()];
        });
    }
}
