<?php

namespace App\Services;

use App\Models\Collaboration;
use App\Models\Creator;
use Illuminate\Support\Collection;

/**
 * Welke creators passen bij een samenwerking, met de reden waarom (uitlegbaar, EU-richtlijn platformwerk).
 * Eén bron voor het team (koppelen) en de ondernemer (zelf kiezen). Afstand is geen harde eis (besluit 14).
 * Harde blokkades, ook in Samenwerking::uitnodigen: alcohol in beeld en jonger dan 25, of al geweigerd.
 */
class Matchlijst
{
    /** Voor het team: alles wat nodig is om te koppelen, inclusief vergoeding. */
    public function voorTeam(Collaboration $c): array
    {
        return $this->bouw($c)->map(fn (array $m) => [
            'id' => $m['creator']->id, 'name' => $m['creator']->user->name, 'level' => $m['creator']->levelName(), 'level_value' => $m['creator']->level,
            'city' => $m['creator']->city, 'instagram' => $m['creator']->instagram, 'tiktok' => $m['creator']->tiktok, 'niches' => $m['creator']->niches ?? [],
            'points' => $m['creator']->points(), 'reward' => $m['creator']->feeCents() > 0 ? 'Gratis bezoek en € '.number_format($m['creator']->feeCents() / 100, 0, ',', '.') : 'Gratis bezoek',
            'reasons' => $m['reasons'], 'blocked' => $m['blocked'],
        ])->values()->all();
    }

    /**
     * Voor de ondernemer: openbaar profiel en kosten in punten, geen vergoeding of leeftijd.
     * Zonder samenwerking (nieuwe aanvraag) volgen vestiging en alcohol pas uit het formulier: daarvoor dient `vanaf_25`.
     */
    public function voorOndernemer(?Collaboration $c = null): array
    {
        return $this->bouw($c)->map(fn (array $m) => [
            'id' => $m['creator']->id, 'name' => $m['creator']->user->name, 'level' => $m['creator']->levelName(), 'level_value' => $m['creator']->level,
            'city' => $m['creator']->city, 'instagram' => $m['creator']->instagram, 'tiktok' => $m['creator']->tiktok, 'niches' => $m['creator']->niches ?? [],
            'kind' => $m['creator']->kind, 'points' => $m['creator']->points(), 'vanaf_25' => ($m['creator']->age() ?? 0) >= 25,
            'reasons' => $m['reasons'], 'blocked' => $m['blocked'],
        ])->values()->all();
    }

    /** @return Collection<int, array{creator: Creator, reasons: list<string>, blocked: ?string}> */
    private function bouw(?Collaboration $c): Collection
    {
        $geweigerd = $c ? $c->invitations()->where('answer', 'geweigerd')->pluck('creator_id')->all() : [];

        return Creator::with('user')->where('status', 'actief')->get()
            ->map(function (Creator $cr) use ($c, $geweigerd) {
                $redenen = [];
                $blokkade = null;
                if ($c && $cr->level === $c->creator_level) {
                    $redenen[] = 'Gevraagd niveau';
                }
                if ($c && $cr->city && $cr->city === $c->location->city) {
                    $redenen[] = 'Woont in '.$cr->city;
                }
                if ($c?->alcohol && ($cr->age() ?? 0) < 25) {
                    $blokkade = 'Jonger dan 25 en er komt alcohol in beeld';
                }
                if (in_array($cr->id, $geweigerd, true)) {
                    $blokkade = 'Heeft deze uitnodiging al afgeslagen';
                }

                return ['creator' => $cr, 'reasons' => $redenen, 'blocked' => $blokkade];
            })
            ->sortBy(fn ($m) => [$m['blocked'] ? 1 : 0, -count($m['reasons']), $m['creator']->user->name])
            ->values();
    }
}
