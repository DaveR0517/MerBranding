<?php

namespace App\Http\Presenters;

use App\Models\Collaboration;
use App\Models\DeliverableVersion;
use App\Models\Status;
use App\Models\User;

/** Zet een samenwerking om naar gegevens voor de schermen. Eén plek, zodat elke omgeving dezelfde vorm krijgt. */
class SamenwerkingPresenter
{
    /** Korte vorm voor lijsten. */
    public static function kort(Collaboration $c): array
    {
        return [
            'id' => $c->id,
            'number' => $c->number,
            'title' => $c->title,
            'status' => self::status($c->status),
            'company' => $c->company->name,
            'location' => $c->location->name,
            'creator' => $c->creator?->user->name,
            'creator_level' => $c->creator_level ? config("mebranding.levels.{$c->creator_level}.name") : null,
            'visit_at' => $c->visit_at?->toIso8601String(),
            'publish_on' => $c->publish_on?->toDateString(),
            'points' => $c->points,
            'updated_at' => $c->updated_at->toIso8601String(),
        ];
    }

    /**
     * Volledige vorm. $voor bepaalt wat zichtbaar is: 'team', 'ondernemer' of 'creator'.
     * Een creator ziet van de ondernemer alleen bedrijfsnaam, vestiging, briefing en wat nodig is voor de klus (CLAUDE.md 5).
     */
    public static function vol(Collaboration $c, string $voor, User $kijker): array
    {
        $c->loadMissing(['company', 'location.visitWindows', 'creator.user', 'versions.feedback.user', 'publications', 'review', 'payout', 'invitations.creator.user']);

        $data = self::kort($c) + [
            'briefing' => $c->briefing,
            'alcohol' => $c->alcohol,
            'period_from' => $c->period_from?->toDateString(),
            'period_to' => $c->period_to?->toDateString(),
            'location_address' => trim(($c->location->address ?? '').', '.($c->location->city ?? ''), ', '),
            'experience' => $c->location->experience,
            'visit_windows' => $c->location->visitWindows->map(fn ($w) => ['weekday' => $w->weekday, 'starts_at' => substr($w->starts_at, 0, 5), 'ends_at' => substr($w->ends_at, 0, 5), 'label' => $w->label()])->values(),
            'visited_at' => $c->visited_at?->toIso8601String(),
            'content_deadline_at' => $c->content_deadline_at?->toIso8601String(),
            'live_at' => $c->live_at?->toIso8601String(),
            'measure_until' => $c->measure_until?->toIso8601String(),
            'cancel_reason' => $c->cancel_reason,
            'reward' => $c->fee_cents > 0 ? 'Gratis bezoek en € '.number_format($c->fee_cents / 100, 0, ',', '.') : 'Gratis bezoek',
            'fee_cents' => $c->fee_cents,
            'versions' => self::versies($c, $voor, $kijker),
            'publications' => $c->publications->map(fn ($p) => ['platform' => $p->platform, 'url' => $p->url, 'posted_at' => $p->posted_at->toIso8601String()])->values(),
            'review' => $c->review ? ['rating' => $c->review->rating, 'body' => $c->review->body] : null,
            'steps' => self::stappen($c),
        ];

        if ($voor === 'creator') {
            unset($data['points']);
        }

        if ($voor !== 'ondernemer') {
            $data['payout'] = $c->payout ? ['amount_cents' => $c->payout->amount_cents, 'status' => $c->payout->status, 'run_on' => $c->payout->run_on->toDateString()] : null;
        }

        if ($voor === 'team') {
            $data['invitations'] = $c->invitations->map(fn ($i) => [
                'id' => $i->id, 'creator' => $i->creator->user->name, 'answer' => $i->answer, 'expires_at' => $i->expires_at->toIso8601String(), 'open' => $i->isOpen(),
            ])->values();
            $data['log'] = $c->log()->with('user')->get()->map(fn ($l) => [
                'at' => $l->created_at->toIso8601String(), 'user' => $l->user?->name ?? 'Systeem', 'event' => $l->event,
                'from' => $l->from_status ? Status::from($l->from_status)->label() : null, 'to' => $l->to_status ? Status::from($l->to_status)->label() : null,
                'reason' => $l->reason, 'data' => $l->data,
            ])->values();
            $data['ledger'] = $c->company->ledger()->where('collaboration_id', $c->id)->latest('id')->get()->map(fn ($l) => [
                'at' => $l->created_at->toIso8601String(), 'kind' => $l->kind, 'points' => $l->points, 'reason' => $l->reason,
            ])->values();
            $data['company_id'] = $c->company_id;
        }

        return $data;
    }

    public static function status(Status $s): array
    {
        return ['value' => $s->value, 'label' => $s->label(), 'number' => $s->number()];
    }

    /** Video's per versie. De ondernemer ziet ze vanaf Ter controle (meekijken met watermerk) en schoon na goedkeuring. */
    private static function versies(Collaboration $c, string $voor, User $kijker): array
    {
        $goedgekeurd = in_array($c->status, [Status::Goedgekeurd, Status::Live, Status::Afgerond], true);

        return $c->versions
            ->filter(fn (DeliverableVersion $v) => $voor !== 'ondernemer' || $goedgekeurd ? true : $v->id === $c->versions->last()?->id && $c->status === Status::TerControle)
            ->filter(fn (DeliverableVersion $v) => $voor !== 'ondernemer' || ! $goedgekeurd || $v->outcome === 'goedgekeurd')
            ->map(fn (DeliverableVersion $v) => [
                'id' => $v->id,
                'version' => $v->version,
                'url' => route('video', $v),
                'download_url' => $voor !== 'ondernemer' || $v->outcome === 'goedgekeurd' ? route('video', [$v, 'download' => 1]) : null,
                'watermark' => $voor === 'ondernemer' && $v->outcome !== 'goedgekeurd',
                'caption' => $v->caption,
                'label_check' => $v->label_check,
                'outcome' => $v->outcome,
                'submitted_at' => $v->created_at->toIso8601String(),
                'feedback' => $v->feedback
                    ->filter(fn ($f) => $voor === 'team' || ($voor === 'creator' ? $f->visible_to_creator : $f->user_id === $kijker->id || $f->visible_to_creator))
                    ->map(fn ($f) => ['id' => $f->id, 'timecode' => $f->timecode, 'body' => $f->body, 'author' => $f->user->name, 'from_owner' => ! $f->visible_to_creator])
                    ->values(),
            ])->values()->all();
    }

    /** De 11 stappen met datum, voor de tijdlijn. */
    private static function stappen(Collaboration $c): array
    {
        $huidig = $c->status->number() ?? 0;

        return collect(Status::cases())->filter(fn ($s) => $s->number())->map(fn (Status $s) => [
            'number' => $s->number(), 'label' => $s->label(), 'done' => $s->number() < $huidig || $c->status === Status::Afgerond, 'current' => $s === $c->status,
        ])->values()->all();
    }
}
