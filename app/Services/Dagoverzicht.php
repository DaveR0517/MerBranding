<?php

namespace App\Services;

use App\Models\MessageTemplate;
use App\Models\User;
use App\Notifications\PlatformMelding;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Dagoverzicht voor het team (t-dagoverzicht): teamsignalen waarvan de mail op "samenvatting" staat, gaan niet los per mail
 * maar komen op werkdagen in één mail. Alleen signalen die nog ongelezen zijn sinds de vorige werkdag.
 */
class Dagoverzicht
{
    public function stuur(?CarbonImmutable $nu = null): int
    {
        $nu ??= CarbonImmutable::now('Europe/Amsterdam');
        $sjabloon = MessageTemplate::firstWhere('key', 't-dagoverzicht');
        $samenvatting = MessageTemplate::where('channels->email', 'samenvatting')->pluck('key')->all();
        if (! $sjabloon || ! $samenvatting) {
            return 0;
        }

        $vanaf = $nu->subWeekday();
        $verstuurd = 0;

        $signalen = DatabaseNotification::query()
            ->where('notifiable_type', (new User)->getMorphClass())
            ->whereNull('read_at')
            ->where('created_at', '>=', $vanaf)
            ->where(fn ($q) => $q->whereIn('data->key', $samenvatting))
            ->latest()
            ->get()
            ->groupBy('notifiable_id');

        foreach ($signalen as $userId => $meldingen) {
            $user = User::find($userId);
            if (! $user?->isTeam()) {
                continue;
            }
            $regels = $meldingen->take(30)->map(fn (DatabaseNotification $n) => [$n->data['title'] ?? '', $n->data['body'] ?? '', $n->data['url'] ?? route('team.werkvoorraad')])->values()->all();

            $user->notify(new PlatformMelding($sjabloon, [
                'voornaam' => str($user->name)->before(' ')->toString(),
                'aantal' => (string) $meldingen->count(),
                'regels' => json_encode($regels),
            ], route('team.werkvoorraad')));
            $verstuurd++;
        }

        return $verstuurd;
    }
}
