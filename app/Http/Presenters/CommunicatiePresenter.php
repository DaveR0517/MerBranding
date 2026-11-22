<?php

namespace App\Http\Presenters;

use App\Http\Presenters\SamenwerkingPresenter as Samenwerking;
use App\Models\Bericht;
use App\Models\Collaboration;
use App\Models\HelpArtikel;
use App\Models\SupportVraag;
use App\Models\User;
use App\Services\Berichten;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Zet berichten, supportvragen en helpartikelen om voor de schermen. Eén vorm voor alle omgevingen. */
class CommunicatiePresenter
{
    /**
     * Inbox: één regel per samenwerking met het laatste bericht en het aantal ongelezen.
     *
     * @param  Collection<int, Collaboration>  $samenwerkingen
     */
    public static function inbox(Collection $samenwerkingen, User $kijker, string $omgeving, Berichten $dienst): array
    {
        $ids = $samenwerkingen->pluck('id')->all();
        $ongelezen = $dienst->ongelezen($ids, $kijker, $omgeving);
        $laatste = Bericht::with('user')->whereIn('collaboration_id', $ids)->whereIn('visible_to', Berichten::kanalen($omgeving))
            ->latest('id')->get()->unique('collaboration_id')->keyBy('collaboration_id');

        return $samenwerkingen->map(function (Collaboration $c) use ($laatste, $ongelezen, $omgeving) {
            $b = $laatste[$c->id] ?? null;

            return [
                'id' => $c->id,
                'number' => $c->number,
                'title' => $c->title,
                'company' => $c->company->name,
                'location' => $c->location->name,
                'creator' => $c->creator?->user->name,
                'status' => Samenwerking::status($c->status),
                'afgerond' => in_array($c->status->value, ['afgerond', 'geannuleerd', 'niet_verschenen'], true),
                'last' => $b ? ['body' => Str::limit($b->body, 90), 'at' => $b->created_at->toIso8601String(), 'author' => $b->user?->name] : null,
                'unread' => $ongelezen[$c->id] ?? 0,
                'url' => route("{$omgeving}.communicatie.berichten.gesprek", $c),
            ];
        })->sortByDesc(fn ($r) => $r['last']['at'] ?? '0')->values()->all();
    }

    /** Het gesprek van één samenwerking, per kanaal. */
    public static function gesprek(Collaboration $c, User $kijker, string $omgeving, Berichten $dienst, string $samenwerkingUrl): array
    {
        $c->loadMissing(['company.users', 'creator.user', 'location']);
        $ondernemers = $c->company->users->pluck('id')->all();
        $creatorId = $c->creator?->user_id;
        $berichten = $dienst->gesprek($c, $omgeving)->groupBy('visible_to');

        return [
            'id' => $c->id,
            'number' => $c->number,
            'title' => $c->title,
            'company' => $c->company->name,
            'location' => $c->location->name,
            'creator' => $c->creator?->user->name,
            'status' => Samenwerking::status($c->status),
            'url' => $samenwerkingUrl,
            'post_url' => route("{$omgeving}.communicatie.berichten.sturen", $c),
            'channels' => collect(Berichten::kanalen($omgeving))->map(fn ($k) => [
                'key' => $k,
                'name' => Berichten::kanaalNaam($k, $omgeving),
                'visibility' => Berichten::zichtbaarVoor($k, $omgeving),
                'can_write' => $k === Bericht::TEAM_ONDERNEMER || (bool) $c->creator_id,
                'messages' => ($berichten[$k] ?? collect())->map(fn (Bericht $b) => [
                    'id' => $b->id,
                    'body' => $b->body,
                    'at' => $b->created_at->toIso8601String(),
                    'mine' => $b->user_id === $kijker->id,
                    'author' => $b->user?->name ?? 'Systeem',
                    'role' => match (true) {
                        $b->user_id === null => 'systeem',
                        $b->user_id === $creatorId => 'creator',
                        in_array($b->user_id, $ondernemers, true) => 'ondernemer',
                        default => 'team',
                    },
                ])->values(),
            ])->values(),
        ];
    }

    public static function artikel(HelpArtikel $a, bool $metBeheer = false): array
    {
        return [
            'id' => $a->id,
            'slug' => $a->slug,
            'title' => $a->title,
            'category' => $a->category,
            'html' => Str::markdown($a->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
        ] + ($metBeheer ? ['body' => $a->body, 'audiences' => $a->audiences, 'position' => $a->position] : []);
    }

    public static function vraagKort(SupportVraag $v): array
    {
        return [
            'id' => $v->id,
            'number' => $v->number,
            'subject' => $v->subject,
            'status' => $v->status,
            'status_label' => SupportVraag::STATUSSEN[$v->status] ?? $v->status,
            'priority' => $v->priority,
            'portal' => $v->portal,
            'user' => $v->user?->name,
            'company' => $v->company?->name,
            'assignee' => $v->assignee?->name,
            'updated_at' => $v->updated_at->toIso8601String(),
            'created_at' => $v->created_at->toIso8601String(),
        ];
    }

    /** Volledige vraag; interne notities alleen voor het team. */
    public static function vraag(SupportVraag $v, bool $team): array
    {
        $v->loadMissing(['replies.user', 'collaboration', 'user', 'company', 'assignee']);

        return self::vraagKort($v) + [
            'assigned_to' => $v->assigned_to,
            'collaboration' => $v->collaboration ? ['id' => $v->collaboration->id, 'number' => $v->collaboration->number, 'title' => $v->collaboration->title] : null,
            'replies' => $v->replies->filter(fn ($r) => $team || ! $r->internal)->map(fn ($r) => [
                'id' => $r->id,
                'body' => $r->body,
                'internal' => $r->internal,
                'author' => $r->user?->name ?? 'Systeem',
                'mine_side' => $r->user_id === $v->user_id,
                'at' => $r->created_at->toIso8601String(),
            ])->values(),
        ];
    }
}
