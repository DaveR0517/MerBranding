<?php

namespace App\Services;

use App\Models\Collaboration;
use App\Models\Task;
use Carbon\CarbonInterface;

/** De werkvoorraad van het team: taken ontstaan en sluiten door statuswijzigingen (CLAUDE.md 7.5). */
class Werkvoorraad
{
    public function open(string $type, string $title, Collaboration $c, string $permission, ?CarbonInterface $due = null): Task
    {
        return Task::firstOrCreate(
            ['type' => $type, 'collaboration_id' => $c->id, 'closed_at' => null],
            ['title' => $title, 'company_id' => $c->company_id, 'creator_id' => $c->creator_id, 'permission' => $permission, 'due_at' => $due],
        );
    }

    /** @param  string|list<string>|null  $types  null sluit alle open taken van de samenwerking */
    public function sluit(Collaboration $c, string|array|null $types = null): void
    {
        Task::where('collaboration_id', $c->id)->whereNull('closed_at')
            ->when($types, fn ($q) => $q->whereIn('type', (array) $types))
            ->update(['closed_at' => now()]);
    }

    /** Deadline contentcontrole: dezelfde werkdag, na de grens de volgende werkdag (VOORSTEL 16:00). */
    public static function controleDeadline(CarbonInterface $binnen): CarbonInterface
    {
        [$uur, $minuut] = array_map('intval', explode(':', config('mebranding.timers.review_cutoff')));
        $dag = $binnen->copy();
        if ($dag->isWeekend() || $dag->gt($dag->copy()->setTime($uur, $minuut))) {
            $dag = $dag->addDay();
            while ($dag->isWeekend()) {
                $dag = $dag->addDay();
            }
        }

        return $dag->setTime(17, 0);
    }
}
