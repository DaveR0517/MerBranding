<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Instellingen die het team zelf beheert (CLAUDE.md 7.4 en 8, zelfbeheer). Elke instelling overschrijft één configpad
 * uit config/mebranding.php; de rest van het platform leest gewoon config() en merkt het verschil niet.
 * Bewerkbaar: punten en vergoeding per niveau, alle timers en de 2FA-plicht voor het team.
 */
class Instellingen
{
    public const CACHE = 'mebranding.instellingen';

    public const CACHE_SECONDEN = 300;

    /** Uitleg per timer. Een timer die een andere module toevoegt, verschijnt vanzelf met een algemene uitleg. */
    private const TIMERS = [
        'invitation_hours' => ['Reactietermijn uitnodiging', 'uur', 'Zo lang heeft een creator om een uitnodiging te accepteren. Daarna vervalt hij en kiest het team de volgende creator.', true],
        'self_confirm_hours' => ['Zelf bevestigen bezoek', 'uur', 'Bij bedrijven die het bezoek zelf bevestigen: zonder reactie binnen deze tijd staat het bezoek vast (besluit W2, besloten).', false],
        'content_deadline_days' => ['Deadline content', 'dagen', 'Aantal dagen na het bezoek waarin de creator de video aanlevert.', true],
        'review_cutoff' => ['Grens contentcontrole', 'tijd', 'Komt een video na dit tijdstip binnen, dan controleert het team hem de volgende werkdag (besluit W4).', true],
        'measure_days' => ['Meetperiode', 'dagen', 'Zo lang meten we views, bereik en interactie vanaf Live (besluit W6, besloten).', false],
        'late_cancel_hours' => ['Laat annuleren', 'uur', 'Annuleert de ondernemer korter dan dit voor het bezoek, dan is het tegoed verbruikt.', true],
    ];

    /** @var array<string, mixed>|null De waarden uit config/mebranding.php, voordat de database ze overschrijft. */
    private ?array $standaard = null;

    /** Zet de waarden uit de database over de config heen. Robuust als de tabel (nog) niet bestaat. */
    public function laad(): void
    {
        $this->standaard ??= config('mebranding', []);

        try {
            $waarden = Cache::remember(self::CACHE, self::CACHE_SECONDEN, fn () => Setting::pluck('value', 'key')->all());
        } catch (Throwable) {
            return; // geen database of nog niet gemigreerd: config/mebranding.php geldt
        }

        foreach ($waarden as $key => $waarde) {
            if ($this->definitie($key)) {
                config([$key => $waarde]);
            }
        }
    }

    /**
     * Alle bewerkbare instellingen met uitleg, standaardwaarde en huidige waarde.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogus(): array
    {
        $opgeslagen = Setting::with('editor')->get()->keyBy('key');

        return collect($this->definities())->map(function (array $d) use ($opgeslagen) {
            $setting = $opgeslagen->get($d['key']);

            return $d + [
                'waarde' => config($d['key']),
                'is_proposal' => $setting ? $setting->is_proposal : $d['voorstel'],
                'gewijzigd' => $setting ? ['door' => $setting->editor?->name, 'op' => $setting->updated_at?->toIso8601String()] : null,
            ];
        })->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function definitie(string $key): ?array
    {
        return $this->definities()[$key] ?? null;
    }

    /**
     * Slaat één instelling op als er iets verandert, logt oud en nieuw, leegt de cache en past de config direct aan.
     * Geeft terug of er iets veranderde.
     */
    public function opslaan(string $key, mixed $waarde, bool $voorstel, User $door): bool
    {
        $d = $this->definitie($key);
        abort_unless($d, 422, 'Onbekende instelling.');

        $oud = config($key);
        $setting = Setting::firstOrNew(['key' => $key]);
        $oudVoorstel = $setting->exists ? $setting->is_proposal : $d['voorstel'];

        if ($oud === $waarde && $oudVoorstel === $voorstel) {
            return false;
        }

        $setting->fill(['value' => $waarde, 'is_proposal' => $voorstel, 'updated_by' => $door->id])->save();

        ActivityLog::create([
            'user_id' => $door->id,
            'event' => 'instelling_gewijzigd',
            'reason' => Str::limit($d['groep'].': '.$d['label'], 250),
            'data' => ['key' => $key, 'oud' => $oud, 'nieuw' => $waarde, 'voorstel_oud' => $oudVoorstel, 'voorstel_nieuw' => $voorstel],
        ]);

        Cache::forget(self::CACHE);
        config([$key => $waarde]);

        return true;
    }

    /** @return array<string, array<string, mixed>> */
    private function definities(): array
    {
        $this->standaard ??= config('mebranding', []);
        $lijst = [];

        foreach ($this->standaard['levels'] ?? [] as $niveau => $l) {
            $lijst[] = $this->maak("levels.{$niveau}.points", 'Niveaus creators', "{$l['name']}: punten per samenwerking", 'getal', 'punten',
                'Zoveel punten van het tegoed kost een samenwerking met een creator van dit niveau.', true, $l['points']);

            // Alleen Platinum en Elite krijgen geld (besloten 18-11-2026): bij de andere niveaus is de vergoeding geen instelling.
            if (($l['fee_cents'] ?? 0) > 0) {
                $lijst[] = $this->maak("levels.{$niveau}.fee_cents", 'Niveaus creators', "{$l['name']}: vergoeding per samenwerking", 'bedrag', 'euro',
                    'Wat de creator krijgt naast het gratis bezoek, opeisbaar bij Live en betaald in de betaalrun op vrijdag.', true, $l['fee_cents']);
            }
        }

        foreach ($this->standaard['timers'] ?? [] as $timer => $waarde) {
            [$label, $eenheid, $uitleg, $voorstel] = self::TIMERS[$timer]
                ?? [Str::ucfirst(str_replace('_', ' ', $timer)), $this->eenheid($timer), 'Timer uit config/mebranding.php.', true];
            $lijst[] = $this->maak("timers.{$timer}", 'Timers', $label, $eenheid === 'tijd' ? 'tijd' : 'getal', $eenheid, $uitleg, $voorstel, $waarde);
        }

        $lijst[] = $this->maak('team_2fa_verplicht', 'Beveiliging', 'Tweestapsverificatie verplicht voor het team', 'aan_uit', null,
            'Teamleden zonder bevestigde tweestapsverificatie gaan eerst naar hun beveiligingspagina. Alleen een Super Admin zet dit uit.', false,
            $this->standaard['team_2fa_verplicht'] ?? true, alleenSuperAdmin: true);

        return Arr::keyBy($lijst, 'key');
    }

    private function eenheid(string $timer): string
    {
        return match (true) {
            str_ends_with($timer, '_hours') => 'uur',
            str_ends_with($timer, '_days') => 'dagen',
            str_ends_with($timer, '_cutoff'), str_ends_with($timer, '_time') => 'tijd',
            default => '',
        };
    }

    /** @return array<string, mixed> */
    private function maak(string $pad, string $groep, string $label, string $type, ?string $eenheid, string $uitleg, bool $voorstel, mixed $standaard, bool $alleenSuperAdmin = false): array
    {
        return [
            'key' => "mebranding.{$pad}", 'groep' => $groep, 'label' => $label, 'type' => $type, 'eenheid' => $eenheid,
            'uitleg' => $uitleg, 'voorstel' => $voorstel, 'standaard' => $standaard, 'alleen_super_admin' => $alleenSuperAdmin,
        ];
    }
}
