<?php

namespace App\Services;

use App\Models\Collaboration;
use App\Models\Creator;
use App\Models\CreditLedger;
use App\Models\Lead;
use App\Models\Payout;
use App\Models\Publication;
use App\Models\Review;
use App\Models\Status;
use App\Models\Subscription;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Het eigenaarsdashboard (AdminStatistieken): kerncijfers met de vergelijking met de vorige periode.
 * Alles wordt berekend uit de ruwe tabellen; niets wordt opgeslagen.
 */
class Kerncijfers
{
    public function __construct(private Cijfers $cijfers) {}

    public function dashboard(?CarbonImmutable $nu = null): array
    {
        $nu ??= CarbonImmutable::now();
        $week = $nu->startOfWeek();
        $vorigeWeek = $week->subWeek();
        $maand = $nu->startOfMonth();
        $vorigeMaand = $maand->subMonth();

        $aanvragen = fn ($van, $tot) => Collaboration::where('created_at', '>=', $van)->where('created_at', '<', $tot)->count();
        $afgerond = fn ($van, $tot) => Collaboration::where('status', Status::Afgerond)->where('completed_at', '>=', $van)->where('completed_at', '<', $tot)->count();
        $oordeel = fn ($van, $tot) => Review::where('created_at', '>=', $van)->where('created_at', '<', $tot)->selectRaw('avg(rating) as gem, count(*) as n')->first();
        $oordeelNu = $oordeel($nu->subDays(90), $nu->addSecond());
        $oordeelVoor = $oordeel($nu->subDays(180), $nu->subDays(90));

        $perStatus = Collaboration::select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');
        $statussen = collect(Status::cases())->filter(fn (Status $s) => $s->number() !== null)->map(fn (Status $s) => [
            'value' => $s->value, 'label' => $s->label(), 'number' => $s->number(),
            'count' => $s === Status::Afgerond ? $afgerond($maand, $nu->addSecond()) : (int) ($perStatus[$s->value] ?? 0),
        ])->values();

        $teLaatContent = Collaboration::whereIn('status', [Status::ContentMaken, Status::Feedback])->where('content_deadline_at', '<', $nu)->count();
        $controleOverTijd = Task::open()->where('type', 'contentcontrole')->where('due_at', '<', $nu)->count();

        $actieveBedrijven = Subscription::where('status', 'actief')->whereDate('starts_on', '<=', $nu->toDateString())
            ->where(fn ($q) => $q->whereNull('cancels_on')->orWhereDate('cancels_on', '>=', $nu->toDateString()))
            ->distinct('company_id')->count('company_id');
        $nieuweBedrijven = Subscription::where('starts_on', '>=', $maand->toDateString())->distinct('company_id')->count('company_id');

        $tegoed = fn ($van, $tot) => [
            'toegekend' => (int) CreditLedger::where('kind', CreditLedger::TOEKENNEN)->where('created_at', '>=', $van)->where('created_at', '<', $tot)->sum('points'),
            // Verbruiken boekt 0 op het saldo (de punten waren al gereserveerd): het verbruik zijn de punten van die samenwerkingen.
            'verbruikt' => (int) CreditLedger::query()->join('collaborations', 'collaborations.id', '=', 'credit_ledger.collaboration_id')
                ->where('credit_ledger.kind', CreditLedger::VERBRUIKEN)->where('credit_ledger.created_at', '>=', $van)->where('credit_ledger.created_at', '<', $tot)
                ->sum('collaborations.points'),
        ];

        $opeisbaar = Payout::where('status', 'opeisbaar');
        $weken = collect(range(7, 0))->map(fn ($i) => $week->subWeeks($i));

        return [
            'bijgewerkt' => $nu->toIso8601String(),
            'kerncijfers' => [
                'aanvragen' => ['nu' => $aanvragen($week, $nu->addSecond()), 'vorige' => $aanvragen($vorigeWeek, $week)],
                'lopend' => $statussen->filter(fn ($s) => $s['number'] < 11)->sum('count'),
                'ter_controle' => (int) ($perStatus[Status::TerControle->value] ?? 0),
                'te_laat' => ['content' => $teLaatContent, 'controle' => $controleOverTijd],
                'afgerond' => ['nu' => $afgerond($maand, $nu->addSecond()), 'vorige' => $afgerond($vorigeMaand, $maand)],
                'oordeel' => [
                    'nu' => $oordeelNu?->n ? round((float) $oordeelNu->gem, 1) : null, 'aantal' => (int) ($oordeelNu?->n ?? 0),
                    'vorige' => $oordeelVoor?->n ? round((float) $oordeelVoor->gem, 1) : null,
                ],
                'bedrijven' => ['actief' => $actieveBedrijven, 'nieuw' => $nieuweBedrijven],
                'creators' => ['actief' => Creator::where('status', 'actief')->count(), 'screening' => Creator::where('status', 'screening')->count()],
                'tegoed' => ['nu' => $tegoed($maand, $nu->addSecond()), 'vorige' => $tegoed($vorigeMaand, $maand)],
                'opeisbaar' => ['bedrag' => (int) (clone $opeisbaar)->sum('amount_cents'), 'aantal' => (clone $opeisbaar)->count(), 'vrijdag' => Geld::vrijdag($nu)->toDateString()],
                'cijfers_controleren' => Cijfers::openTaken(),
            ],
            'statussen' => $statussen,
            'aanvragen_per_week' => $weken->map(fn ($w) => ['week' => $w->toDateString(), 'label' => 'wk '.$w->isoWeek, 'aantal' => $aanvragen($w, $w->addWeek())])->values(),
            'leads_per_week' => $weken->map(fn ($w) => [
                'week' => $w->toDateString(), 'label' => 'wk '.$w->isoWeek,
                'ondernemer' => Lead::where('type', Lead::TYPE_ONDERNEMER)->where('created_at', '>=', $w)->where('created_at', '<', $w->addWeek())->count(),
                'creator' => Lead::where('type', Lead::TYPE_CREATOR)->where('created_at', '>=', $w)->where('created_at', '<', $w->addWeek())->count(),
            ])->values(),
            'metrics' => Cijfers::metrics(),
            'platforms' => Cijfers::perPlatform($this->cijfers->perPublicatie(Publication::where('posted_at', '>=', $nu->subDays(84))->get())),
        ];
    }
}
