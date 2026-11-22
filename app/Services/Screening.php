<?php

namespace App\Services;

use App\Mail\ScreeningBericht;
use App\Models\ActivityLog;
use App\Models\Creator;
use App\Models\CreatorProfiel;
use App\Models\Lead;
use App\Models\ScreeningAanmelding;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Screening van aanmeldingen als creator (docs/02 flow 2, stap 3 tot 5).
 * Altijd een besluit van een teamlid, met reden, in het logboek (CLAUDE.md 8, platformwerk).
 */
class Screening
{
    public function __construct(private Melder $melder) {}

    /** Nieuwe aanmelding van de website: een taak in de werkvoorraad. */
    public function aangemeld(Lead $lead): ScreeningAanmelding
    {
        $dagen = (int) config('mebranding.timers.screening_workdays', 2); // VOORSTEL: 2 werkdagen
        $taak = Task::create([
            'type' => 'screening',
            'title' => "Aanmelding creator screenen: {$lead->name}",
            'permission' => 'creators.screenen',
            'due_at' => now()->addWeekdays($dagen),
        ]);

        return ScreeningAanmelding::updateOrCreate(['lead_id' => $lead->id], ['task_id' => $taak->id]);
    }

    public function goedkeuren(Lead $lead, User $door, string $niveau, string $reden): Creator
    {
        $this->magBeslissen($lead);
        if (! array_key_exists($niveau, config('mebranding.levels'))) {
            throw ValidationException::withMessages(['level' => 'Kies een bestaand niveau.']);
        }

        return DB::transaction(function () use ($lead, $door, $niveau, $reden) {
            $data = $lead->data ?? [];
            $user = User::firstOrCreate(['email' => Str::lower($lead->email)], ['name' => $lead->name, 'password' => Str::password(40)]);
            $user->assignRole('Creator');

            $creator = Creator::updateOrCreate(['user_id' => $user->id], [
                'level' => $niveau,
                'kind' => in_array($data['soort'] ?? null, array_keys(CreatorProfiel::SOORTEN), true) ? $data['soort'] : 'beide',
                'niches' => $this->niches($data['niches'] ?? []),
                'birthdate' => $data['geboortedatum'] ?? null,
                'city' => $lead->city,
                'instagram' => $this->handle($data['instagram-naam'] ?? null),
                'tiktok' => $this->handle($data['tiktok-naam'] ?? null),
                'status' => 'actief',
            ]);
            $profiel = CreatorProfiel::van($creator);
            $profiel->radius_km ??= is_numeric($data['straal'] ?? null) ? (int) $data['straal'] : null;
            $profiel->save();

            $lead->update(['status' => 'creator']);
            $this->besluit($lead, $door, ScreeningAanmelding::GOEDGEKEURD, $reden, $niveau, $creator);

            $token = Password::broker()->createToken($user);
            $this->melder->stuur('c-welkom', $user, ['niveau' => $creator->levelName()], route('password.reset', ['token' => $token, 'email' => $user->email]));

            return $creator;
        });
    }

    public function aanvullingVragen(Lead $lead, User $door, string $toelichting): void
    {
        $this->magBeslissen($lead);
        DB::transaction(function () use ($lead, $door, $toelichting) {
            $lead->update(['status' => 'aanvulling']);
            $this->besluit($lead, $door, ScreeningAanmelding::AANVULLING, $toelichting);
            Mail::to($lead->email, $lead->name)->queue(new ScreeningBericht('c-aanvulling', $lead, ['toelichting' => $toelichting], route('site.creators')));
        });
    }

    public function afwijzen(Lead $lead, User $door, string $reden): void
    {
        $this->magBeslissen($lead);
        DB::transaction(function () use ($lead, $door, $reden) {
            $lead->update(['status' => 'afgewezen']);
            $this->besluit($lead, $door, ScreeningAanmelding::AFGEWEZEN, $reden);
            // VOORSTEL: de academy heeft nog geen openbare pagina; de knop gaat naar de creatorpagina van de website.
            Mail::to($lead->email, $lead->name)->queue(new ScreeningBericht('c-afgewezen', $lead, ['reden' => $reden], route('site.creators')));
        });
    }

    /** Alleen aanmeldingen als creator die nog openstaan (nieuw of wacht op aanvulling). */
    private function magBeslissen(Lead $lead): void
    {
        if ($lead->type !== Lead::TYPE_CREATOR || ! in_array($lead->status, ['nieuw', 'aanvulling'], true)) {
            throw ValidationException::withMessages(['lead' => 'Over deze aanmelding is al besloten.']);
        }
    }

    private function besluit(Lead $lead, User $door, string $besluit, string $reden, ?string $niveau = null, ?Creator $creator = null): void
    {
        $screening = ScreeningAanmelding::firstOrNew(['lead_id' => $lead->id]);
        $screening->fill([
            'decision' => $besluit, 'reason' => $reden, 'level' => $niveau, 'creator_id' => $creator?->id,
            'decided_by' => $door->id, 'decided_at' => now(),
        ])->save();

        // Aanvulling gevraagd: de taak blijft niet open bij het team, de aanmelder is aan zet.
        if ($screening->task_id) {
            Task::whereKey($screening->task_id)->whereNull('closed_at')->update(['closed_at' => now(), 'creator_id' => $creator?->id]);
        }

        ActivityLog::create([
            'user_id' => $door->id, 'subject_type' => $lead->getMorphClass(), 'subject_id' => $lead->id,
            'event' => "screening_{$besluit}", 'reason' => Str::limit($reden, 250, ''),
            'data' => array_filter(['niveau' => $niveau, 'creator_id' => $creator?->id, 'naam' => $lead->name]),
        ]);
    }

    /** Niches van het formulier (sleutel of label) als label. */
    private function niches(mixed $niches): array
    {
        return collect((array) $niches)
            ->map(fn ($n) => CreatorProfiel::NICHES[$n] ?? (in_array($n, CreatorProfiel::NICHES, true) ? $n : null))
            ->filter()->unique()->values()->all();
    }

    private function handle(?string $naam): ?string
    {
        $naam = trim((string) $naam);

        return $naam === '' ? null : '@'.ltrim($naam, '@');
    }
}
