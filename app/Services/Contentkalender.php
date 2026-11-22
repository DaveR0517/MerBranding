<?php

namespace App\Services;

use App\Models\Collaboration;
use App\Models\Company;
use App\Models\ContentNote;
use App\Models\Status;
use Carbon\CarbonImmutable;

/**
 * Maandkalender per bedrijf: bezoeken, publicatiedata en livegangen uit de samenwerkingen (alleen lezen)
 * plus eigen notities en ideeën.
 */
class Contentkalender
{
    public static function maand(?string $maand): CarbonImmutable
    {
        $start = $maand && preg_match('/^\d{4}-\d{2}$/', $maand) ? CarbonImmutable::createFromFormat('Y-m-d', "{$maand}-01") : null;

        return ($start ?: CarbonImmutable::today())->startOfMonth();
    }

    /** @return array<string, mixed> */
    public function voor(Company $bedrijf, CarbonImmutable $maand): array
    {
        $van = $maand->startOfMonth();
        $tot = $maand->endOfMonth();
        $items = [];

        $samenwerkingen = Collaboration::with(['location', 'creator.user'])->where('company_id', $bedrijf->id)
            ->whereNotIn('status', [Status::Geannuleerd->value, Status::NietVerschenen->value])
            ->where(fn ($q) => $q->whereBetween('visit_at', [$van, $tot])->orWhereBetween('publish_on', [$van->toDateString(), $tot->toDateString()])->orWhereBetween('live_at', [$van, $tot]))
            ->get();

        foreach ($samenwerkingen as $c) {
            $basis = ['samenwerking_id' => $c->id, 'titel' => $c->title, 'vestiging' => $c->location->name, 'creator' => $c->creator?->user->name, 'status' => $c->status->label()];
            if ($c->visit_at?->between($van, $tot)) {
                $items[] = $basis + ['soort' => 'bezoek', 'datum' => $c->visit_at->toDateString(), 'tijd' => $c->visit_at->format('H:i')];
            }
            if ($c->publish_on?->between($van, $tot) && ! $c->live_at) {
                $items[] = $basis + ['soort' => 'publicatie', 'datum' => $c->publish_on->toDateString(), 'tijd' => null];
            }
            if ($c->live_at?->between($van, $tot)) {
                $items[] = $basis + ['soort' => 'live', 'datum' => $c->live_at->toDateString(), 'tijd' => $c->live_at->format('H:i')];
            }
        }

        foreach (ContentNote::with('user')->where('company_id', $bedrijf->id)->whereBetween('date', [$van->toDateString(), $tot->toDateString()])->get() as $n) {
            $items[] = ['soort' => 'notitie', 'id' => $n->id, 'titel' => $n->title, 'omschrijving' => $n->body, 'datum' => $n->date->toDateString(), 'tijd' => null, 'door' => $n->user?->name];
        }

        usort($items, fn ($a, $b) => [$a['datum'], $a['tijd'] ?? ''] <=> [$b['datum'], $b['tijd'] ?? '']);

        return [
            'maand' => $van->format('Y-m'),
            'label' => $van->translatedFormat('F Y'),
            'vorige' => $van->subMonth()->format('Y-m'),
            'volgende' => $van->addMonth()->format('Y-m'),
            'items' => $items,
        ];
    }
}
