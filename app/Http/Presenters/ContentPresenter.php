<?php

namespace App\Http\Presenters;

use App\Models\UsageRight;
use App\Models\VideoIdea;
use App\Services\Gebruiksrechten;
use Illuminate\Http\Request;

/** Vertaalt de modellen van de module content naar props voor de schermen, en valideert de gedeelde formulieren. */
class ContentPresenter
{
    /** @return array{date: string, title: string, body: string|null} */
    public static function valideerNotitie(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** @return array{title: string, description: string|null} */
    public static function valideerIdee(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** @return array<string, mixed> */
    public static function idee(VideoIdea $i): array
    {
        return [
            'id' => $i->id,
            'title' => $i->title,
            'description' => $i->description,
            'status' => $i->status,
            'source' => $i->source,
            'created_at' => $i->created_at?->toIso8601String(),
            'collaboration' => $i->collaboration ? ['id' => $i->collaboration->id, 'title' => $i->collaboration->title, 'number' => $i->collaboration->number] : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function recht(UsageRight $r): array
    {
        $c = $r->collaboration;

        return [
            'id' => $r->id,
            'kind' => $r->kind,
            'starts_on' => $r->starts_on->toDateString(),
            'ends_on' => $r->ends_on->toDateString(),
            'days_left' => $r->daysLeft(),
            'status' => $r->status,
            'ad_code' => $r->ad_code,
            'is_proposal' => $r->is_proposal,
            'extension_requested_at' => $r->extension_requested_at?->toIso8601String(),
            'collaboration' => ['id' => $c->id, 'title' => $c->title, 'number' => $c->number, 'creator' => $c->creator?->user->name],
        ];
    }

    /** @return array{organisch: int, advertentie: int, melden: list<int>} */
    public static function standaardRechten(): array
    {
        return [
            'organisch' => Gebruiksrechten::standaardMaanden('organisch'),
            'advertentie' => Gebruiksrechten::standaardMaanden('advertentie'),
            'melden' => array_map('intval', config('content.usage_rights.notify_days')),
        ];
    }
}
