<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\SupportVraag;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NieuwBericht;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Supportvragen: indienen vanuit elke omgeving, beantwoorden door het team (recht support.beheren).
 * Een open vraag staat als taak (type support) in de werkvoorraad; de taak sluit bij een antwoord en opent weer bij een reactie.
 */
class Support
{
    public const RECHT = 'support.beheren';

    public function __construct(private Melder $melder) {}

    /** @param array{subject: string, body: string, priority?: string|null, collaboration_id?: int|null} $data */
    public function indienen(User $user, string $omgeving, array $data): SupportVraag
    {
        return DB::transaction(function () use ($user, $omgeving, $data) {
            $c = ! empty($data['collaboration_id']) ? Collaboration::find($data['collaboration_id']) : null;
            $bedrijf = $c?->company ?? ($omgeving === 'ondernemer' ? $user->companies()->first() : null);

            $vraag = SupportVraag::create([
                'number' => 'tijdelijk-'.uniqid(),
                'user_id' => $user->id,
                'portal' => $omgeving,
                'company_id' => $bedrijf?->id,
                'collaboration_id' => $c?->id,
                'assigned_to' => $bedrijf?->account_manager_id,
                'subject' => $data['subject'],
                'priority' => $data['priority'] ?? 'normaal',
                'status' => 'open',
            ]);
            $vraag->update(['number' => 'MB-S-'.(1000 + $vraag->id)]);
            $vraag->replies()->create(['user_id' => $user->id, 'body' => $data['body']]);

            $this->log($vraag, $user, 'support_ingediend');
            $this->taakOpen($vraag);

            $ontvangers = $vraag->assigned_to ? User::whereKey($vraag->assigned_to)->get() : $this->melder->team(self::RECHT);
            Notification::send($ontvangers, new NieuwBericht("Nieuwe supportvraag {$vraag->number}", $vraag->subject, route('team.communicatie.support.vraag', $vraag), 'support'));

            return $vraag;
        });
    }

    /** Antwoord of interne notitie van het team. */
    public function antwoorden(SupportVraag $vraag, User $teamlid, string $tekst, bool $intern = false): void
    {
        $vraag->replies()->create(['user_id' => $teamlid->id, 'body' => $tekst, 'internal' => $intern]);
        if ($intern) {
            return;
        }

        $vraag->update(['status' => 'beantwoord', 'assigned_to' => $vraag->assigned_to ?? $teamlid->id]);
        $this->taakSluit($vraag);
        $this->log($vraag, $teamlid, 'support_beantwoord');
        $vraag->user->notify(new NieuwBericht("Antwoord op je vraag {$vraag->number}", $vraag->subject, route("{$vraag->portal}.communicatie.help.vraag", $vraag), 'support'));
    }

    /** Reactie van wie de vraag stelde: de vraag staat weer open voor het team. */
    public function reageren(SupportVraag $vraag, User $user, string $tekst): void
    {
        $vraag->replies()->create(['user_id' => $user->id, 'body' => $tekst]);
        $vraag->update(['status' => 'open', 'closed_at' => null]);
        $this->taakOpen($vraag);
    }

    public function status(SupportVraag $vraag, User $teamlid, string $status, ?string $prioriteit = null, ?int $toegewezen = null): void
    {
        $van = $vraag->status;
        $vraag->update([
            'status' => $status,
            'priority' => $prioriteit ?? $vraag->priority,
            'assigned_to' => $toegewezen,
            'closed_at' => $status === 'gesloten' ? now() : null,
        ]);
        $this->taak($vraag)->update(['owner_id' => $toegewezen]);
        $status === 'open' ? $this->taakOpen($vraag) : $this->taakSluit($vraag);
        if ($van !== $status) {
            $this->log($vraag, $teamlid, 'support_status', $van, $status);
        }
    }

    /** Teamleden die supportvragen beantwoorden. */
    public function medewerkers()
    {
        return $this->melder->team(self::RECHT)->sortBy('name')->values();
    }

    /** Termijn eerste reactie: VOORSTEL 1 werkdag (AdminSupport). */
    public static function termijn(CarbonInterface $vanaf): CarbonInterface
    {
        $dag = $vanaf->copy();
        $werkdagen = (int) config('mebranding.support.reactie_werkdagen', 1);
        while ($werkdagen > 0) {
            $dag = $dag->addDay();
            if (! $dag->isWeekend()) {
                $werkdagen--;
            }
        }

        return $dag;
    }

    private function taakOpen(SupportVraag $vraag): void
    {
        if ($this->taak($vraag)->exists()) {
            return;
        }
        Task::create([
            'type' => 'support',
            'title' => "{$vraag->number}: {$vraag->subject}",
            'collaboration_id' => $vraag->collaboration_id,
            'company_id' => $vraag->company_id,
            'permission' => self::RECHT,
            'owner_id' => $vraag->assigned_to,
            'due_at' => self::termijn(now()),
        ]);
    }

    private function taakSluit(SupportVraag $vraag): void
    {
        $this->taak($vraag)->update(['closed_at' => now()]);
    }

    /** De open werkvoorraadtaak van deze vraag (de taak draagt het vraagnummer vooraan in de titel). */
    private function taak(SupportVraag $vraag)
    {
        return Task::open()->where('type', 'support')->where('title', 'like', "{$vraag->number}:%");
    }

    private function log(SupportVraag $vraag, User $user, string $event, ?string $van = null, ?string $naar = null): void
    {
        ActivityLog::create([
            'user_id' => $user->id, 'subject_type' => $vraag->getMorphClass(), 'subject_id' => $vraag->id,
            'event' => $event, 'from_status' => $van, 'to_status' => $naar, 'data' => ['nummer' => $vraag->number],
        ]);
    }
}
