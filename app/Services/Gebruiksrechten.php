<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\UsageRight;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Gebruiksrechten op video's (fase 7, open punt 7). De standaardduur is een VOORSTEL uit config/content.php.
 * Rechten starten zodra de video live staat; de eigenaar krijgt 30 en 7 dagen voor het einde een melding (o-rechten).
 */
class Gebruiksrechten
{
    public function __construct(private Melder $melder, private Werkvoorraad $werk) {}

    /** Standaardrechten bij Live: organisch en advertenties, elk met de voorgestelde duur. Dubbel aanmaken kan niet. */
    public function standaard(Collaboration $c, ?CarbonInterface $start = null): void
    {
        $start = ($start ?? $c->live_at ?? now())->copy()->startOfDay();
        $versie = $c->approvedVersion()->value('id');

        foreach (UsageRight::SOORTEN as $soort) {
            UsageRight::firstOrCreate(['collaboration_id' => $c->id, 'kind' => $soort], [
                'deliverable_version_id' => $versie,
                'starts_on' => $start,
                'ends_on' => $start->copy()->addMonths(self::standaardMaanden($soort)),
                'status' => 'actief',
                'is_proposal' => true,
            ]);
        }
    }

    public static function standaardMaanden(string $soort): int
    {
        return (int) config("content.usage_rights.{$soort}_months");
    }

    /**
     * Dagelijks: verlopen rechten op verlopen zetten en de eigenaar melden bij 30 en 7 dagen voor het einde.
     * Eén melding per drempel; valt een recht meteen onder twee drempels, dan één melding.
     * Alleen advertentierechten: daarover gaat het sjabloon o-rechten (verlengen voor advertenties).
     */
    public function meld(): int
    {
        UsageRight::where('status', 'actief')->whereDate('ends_on', '<', today())->update(['status' => 'verlopen']);

        $drempels = collect(config('content.usage_rights.notify_days'))->map(fn ($d) => (int) $d)->sortDesc()->values();
        $verstuurd = 0;

        UsageRight::with('collaboration.company')->where('status', 'actief')->where('kind', 'advertentie')
            ->whereDate('ends_on', '>=', today())
            ->whereDate('ends_on', '<=', today()->addDays($drempels->max()))
            ->get()
            ->each(function (UsageRight $recht) use ($drempels, &$verstuurd) {
                $over = $recht->daysLeft();
                $gemeld = $recht->notified ?? [];
                $nieuw = $drempels->filter(fn ($d) => $over <= $d && ! in_array($d, $gemeld, true))->values();
                if ($nieuw->isEmpty()) {
                    return;
                }

                $c = $recht->collaboration;
                $this->melder->stuur('o-rechten', $c->company->owners()->get(), [
                    'aantal' => 1,
                    'samenwerking' => $c->title,
                    'datum' => $recht->ends_on->translatedFormat('l j F Y'),
                ], route('ondernemer.content.rechten'));

                $recht->update(['notified' => array_values(array_unique([...$gemeld, ...$nieuw->all()]))]);
                $verstuurd++;
            });

        return $verstuurd;
    }

    /** Verlenging aanvragen: een taak voor de accountmanager. Prijs nog niet bepaald (open punt 7). */
    public function verlengen(UsageRight $recht, User $door): void
    {
        $c = $recht->collaboration;
        $recht->update(['extension_requested_at' => now()]);
        $this->werk->open('rechten_verlengen', "Rechten verlengen: {$c->title} ({$c->company->name})", $c, 'bedrijven.beheren', $recht->ends_on);
        ActivityLog::create([
            'user_id' => $door->id, 'subject_type' => $c->getMorphClass(), 'subject_id' => $c->id, 'event' => 'rechten_verlengen_aangevraagd',
            'data' => ['recht' => $recht->id, 'soort' => $recht->kind, 'eind' => $recht->ends_on->toDateString()],
        ]);
    }
}
