<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Publication;
use App\Models\StatSnapshot;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/**
 * Cijfers per publicatie (CLAUDE.md 8 statistieken, W6). Ruwe metingen staan in stat_snapshots;
 * alles wat hier wordt opgeteld of berekend, wordt niet opgeslagen. Elk proces kan handmatig:
 * de creator voert cijfers in met een screenshot, het team controleert of corrigeert met een logregel.
 */
class Cijfers
{
    public const TAAK = 'cijfers-controleren';

    public function __construct(private Melder $melder, private Werkvoorraad $werk) {}

    /** @return array<string, string> */
    public static function metrics(): array
    {
        return config('geld.metrics');
    }

    /** Het recht om cijfers in te voeren of te corrigeren. 'cijfers.invoeren' als dat bestaat, anders samenwerkingen.bekijken. */
    public static function recht(): string
    {
        return Permission::where('name', 'cijfers.invoeren')->exists() ? 'cijfers.invoeren' : 'samenwerkingen.bekijken';
    }

    public static function magTeam(User $user): bool
    {
        return $user->can('cijfers.invoeren') || $user->can('samenwerkingen.bekijken');
    }

    /** Mag de creator nu cijfers invoeren? Alleen tijdens de meetperiode van 14 dagen na Live. */
    public static function meetperiodeLoopt(Collaboration $c): bool
    {
        return $c->status === Status::Live && $c->measure_until?->isFuture();
    }

    /**
     * Handmatige cijfers vastleggen. De creator alleen tijdens de meetperiode en met screenshot;
     * het team altijd, met een reden (screenshot mag).
     *
     * @param  array<string, int|string|null>  $waarden
     * @return Collection<int, StatSnapshot>
     */
    public function invoeren(Publication $p, User $door, array $waarden, ?UploadedFile $screenshot, bool $doorTeam, ?string $reden = null): Collection
    {
        $c = Collaboration::with('company', 'creator.user')->findOrFail($p->collaboration_id);
        $waarden = array_filter(array_intersect_key($waarden, self::metrics()), fn ($v) => $v !== null && $v !== '');

        if ($waarden === []) {
            throw ValidationException::withMessages(['views' => 'Vul minimaal één cijfer in.']);
        }
        if (! $doorTeam) {
            abort_unless($c->creator?->user_id === $door->id, 403);
            if (! self::meetperiodeLoopt($c)) {
                throw ValidationException::withMessages(['views' => 'Cijfers invoeren kan alleen tijdens de meetperiode van '.config('mebranding.timers.measure_days').' dagen na Live.']);
            }
            if (! $screenshot) {
                throw ValidationException::withMessages(['screenshot' => 'Voeg een screenshot toe waarop de cijfers te zien zijn.']);
            }
        } elseif (blank($reden)) {
            throw ValidationException::withMessages(['reason' => 'Geef een reden, bijvoorbeeld waar de cijfers vandaan komen.']);
        }

        return DB::transaction(function () use ($c, $p, $door, $waarden, $screenshot, $doorTeam, $reden) {
            $pad = $screenshot?->storeAs("cijfers/{$c->number}", 'screenshot-'.now()->format('Ymd-His').'-'.Str::random(6).'.'.($screenshot->getClientOriginalExtension() ?: 'png'), 'local');
            $nu = now();

            $regels = collect($waarden)->map(fn ($v, $metric) => StatSnapshot::create([
                'publication_id' => $p->id, 'metric' => $metric, 'value' => (int) $v, 'source' => StatSnapshot::HANDMATIG,
                'period' => 'totaal', 'fetched_at' => $nu, 'is_estimate' => false, 'screenshot_path' => $pad,
                'entered_by' => $door->id, 'note' => $reden,
                'reviewed_at' => $doorTeam ? $nu : null, 'reviewed_by' => $doorTeam ? $door->id : null,
            ]))->values();

            $this->log($c, $door, 'cijfers_ingevoerd', $reden, ['publicatie' => $p->id, 'platform' => $p->platform, 'cijfers' => array_map('intval', $waarden), 'door_team' => $doorTeam]);

            if (! $doorTeam) {
                $creator = $c->creator->user->name;
                $this->werk->open(self::TAAK, "Handmatige cijfers controleren: {$creator}, {$c->company->name}", $c, self::recht(), now()->addWeekday());
                $this->melder->stuur('t-cijfers-handmatig', $this->melder->team(self::recht()), [
                    'creator' => $creator, 'zaak' => $c->company->name, 'samenwerking' => $c->title,
                ], route('team.cijfers', ['samenwerking' => $c->id]));
            }

            return $regels;
        });
    }

    /** Het team corrigeert één cijfer. De oude regel blijft bestaan (ruwe data), de nieuwe vervangt hem. */
    public function corrigeren(StatSnapshot $oud, int $waarde, User $door, string $reden): StatSnapshot
    {
        if (StatSnapshot::where('replaces_id', $oud->id)->exists()) {
            throw ValidationException::withMessages(['value' => 'Deze regel is al gecorrigeerd. Corrigeer de nieuwste.']);
        }

        return DB::transaction(function () use ($oud, $waarde, $door, $reden) {
            $nieuw = StatSnapshot::create([
                'publication_id' => $oud->publication_id, 'metric' => $oud->metric, 'value' => $waarde, 'source' => StatSnapshot::HANDMATIG,
                'period' => $oud->period, 'fetched_at' => $oud->fetched_at, 'is_estimate' => $oud->is_estimate,
                'screenshot_path' => $oud->screenshot_path, 'entered_by' => $door->id, 'replaces_id' => $oud->id, 'note' => $reden,
                'reviewed_at' => now(), 'reviewed_by' => $door->id,
            ]);
            $oud->update(['reviewed_at' => $oud->reviewed_at ?? now(), 'reviewed_by' => $oud->reviewed_by ?? $door->id]);

            $c = Collaboration::findOrFail($oud->publication->collaboration_id);
            $this->log($c, $door, 'cijfers_gecorrigeerd', $reden, ['metriek' => $oud->metric, 'van' => $oud->value, 'naar' => $waarde, 'bron_was' => $oud->source]);
            $this->sluitTaakAlsKlaar($c);

            return $nieuw;
        });
    }

    /** Het team bevestigt dat de handmatige cijfers kloppen met de screenshot. */
    public function akkoord(Collaboration $c, User $door): int
    {
        $aantal = StatSnapshot::whereIn('publication_id', $c->publications()->select('id'))
            ->where('source', StatSnapshot::HANDMATIG)->whereNull('reviewed_at')
            ->update(['reviewed_at' => now(), 'reviewed_by' => $door->id]);

        $this->log($c, $door, 'cijfers_gecontroleerd', null, ['regels' => $aantal]);
        $this->sluitTaakAlsKlaar($c);

        return $aantal;
    }

    /**
     * De nieuwste geldige meting per cijfer, per publicatie. Berekend, niet opgeslagen.
     *
     * @param  Collection<int, Publication>  $publicaties
     * @return list<array<string, mixed>>
     */
    public function perPublicatie(Collection $publicaties): array
    {
        $snapshots = StatSnapshot::geldig()->whereIn('publication_id', $publicaties->pluck('id'))
            ->orderBy('fetched_at')->orderBy('id')->get()->groupBy('publication_id');

        return $publicaties->map(function (Publication $p) use ($snapshots) {
            $laatste = ($snapshots[$p->id] ?? collect())->keyBy('metric'); // de laatste wint
            $metingen = collect(self::metrics())->map(fn ($label, $m) => isset($laatste[$m]) ? [
                'id' => $laatste[$m]->id, 'value' => $laatste[$m]->value, 'source' => $laatste[$m]->source,
                'fetched_at' => $laatste[$m]->fetched_at->toIso8601String(), 'is_estimate' => $laatste[$m]->is_estimate,
                'reviewed' => $laatste[$m]->source !== StatSnapshot::HANDMATIG || $laatste[$m]->reviewed_at !== null,
            ] : null)->filter();

            return [
                'id' => $p->id, 'collaboration_id' => $p->collaboration_id, 'platform' => $p->platform, 'url' => $p->url,
                'posted_at' => $p->posted_at->toIso8601String(),
                'metrics' => $metingen->all(),
                'sources' => $metingen->pluck('source')->unique()->values()->all(),
                'fetched_at' => $metingen->max('fetched_at'),
                'is_estimate' => $metingen->contains('is_estimate', true),
                'unreviewed' => $metingen->contains('reviewed', false),
                'interaction' => $this->interactie($metingen->map(fn ($m) => $m['value'])->all()),
            ];
        })->values()->all();
    }

    /**
     * Totalen per platform. Over platforms en bronnen heen wordt niet opgeteld (CLAUDE.md 8).
     *
     * @param  list<array<string, mixed>>  $perPublicatie
     * @return list<array<string, mixed>>
     */
    public static function perPlatform(array $perPublicatie): array
    {
        return collect($perPublicatie)->groupBy('platform')->map(function (Collection $rijen, string $platform) {
            // null als geen enkele post dit cijfer heeft: "geen cijfer" is iets anders dan 0.
            $totaal = collect(self::metrics())->map(fn ($l, $m) => $rijen->contains(fn ($r) => isset($r['metrics'][$m]))
                ? $rijen->sum(fn ($r) => $r['metrics'][$m]['value'] ?? 0) : null)->all();

            return [
                'platform' => $platform,
                'posts' => $rijen->count(),
                'posts_met_cijfers' => $rijen->filter(fn ($r) => $r['metrics'] !== [])->count(),
                'totals' => $totaal,
                'sources' => $rijen->pluck('sources')->flatten()->unique()->values()->all(),
                'is_estimate' => $rijen->contains('is_estimate', true),
                'fetched_at' => $rijen->max('fetched_at'),
            ];
        })->sortKeys()->values()->all();
    }

    /** Interactie = likes + reacties + shares + opgeslagen (berekend). */
    private function interactie(array $waarden): ?int
    {
        $delen = array_intersect_key($waarden, array_flip(['likes', 'comments', 'shares', 'saves']));

        return $delen === [] ? null : array_sum($delen);
    }

    private function sluitTaakAlsKlaar(Collaboration $c): void
    {
        $open = StatSnapshot::whereIn('publication_id', $c->publications()->select('id'))
            ->where('source', StatSnapshot::HANDMATIG)->whereNull('reviewed_at')->exists();
        if (! $open) {
            $this->werk->sluit($c, self::TAAK);
        }
    }

    private function log(Collaboration $c, ?User $door, string $event, ?string $reden, array $data): void
    {
        ActivityLog::create([
            'user_id' => $door?->id, 'subject_type' => $c->getMorphClass(), 'subject_id' => $c->id,
            'event' => $event, 'reason' => $reden, 'data' => $data,
        ]);
    }

    /** Open taken voor handmatige cijfers (voor tellingen op het dashboard). */
    public static function openTaken(): int
    {
        return Task::whereNull('closed_at')->where('type', self::TAAK)->count();
    }
}
