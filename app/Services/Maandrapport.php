<?php

namespace App\Services;

use App\Http\Presenters\SamenwerkingPresenter;
use App\Models\Collaboration;
use App\Models\Company;
use App\Models\CreditLedger;
use App\Models\Publication;
use Carbon\CarbonImmutable;

/** Maandrapport per bedrijf per maand: samenwerkingen, cijfers en tegoedverbruik (losse-schermen/maandrapport.html). */
class Maandrapport
{
    public function __construct(private Cijfers $cijfers) {}

    /** Maand als JJJJ-MM; ongeldig of leeg wordt de huidige maand. */
    public static function maand(?string $maand): CarbonImmutable
    {
        return $maand && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $maand)
            ? CarbonImmutable::createFromFormat('Y-m-d', "{$maand}-01")->startOfDay()
            : CarbonImmutable::now()->startOfMonth();
    }

    /** De maanden waarvoor een rapport kan: vanaf het eerste abonnement of de eerste samenwerking tot nu. */
    public static function maanden(Company $bedrijf): array
    {
        $eerste = collect([$bedrijf->subscriptions()->min('starts_on'), $bedrijf->collaborations()->min('created_at')])->filter()->min();
        $van = CarbonImmutable::parse($eerste ?? now())->startOfMonth();
        $lijst = [];
        for ($m = CarbonImmutable::now()->startOfMonth(); $m->gte($van) && count($lijst) < 24; $m = $m->subMonth()) {
            $lijst[] = ['value' => $m->format('Y-m'), 'label' => $m->translatedFormat('F Y')];
        }

        return $lijst;
    }

    public function voor(Company $bedrijf, CarbonImmutable $maand): array
    {
        $van = $maand->startOfMonth();
        $tot = $van->addMonth();
        $inMaand = fn ($q, $kolom) => $q->where($kolom, '>=', $van)->where($kolom, '<', $tot);

        $samenwerkingen = Collaboration::with(['company', 'location', 'creator.user'])->where('company_id', $bedrijf->id)
            ->where(fn ($q) => $q->where(fn ($q) => $inMaand($q, 'created_at'))->orWhere(fn ($q) => $inMaand($q, 'live_at'))->orWhere(fn ($q) => $inMaand($q, 'completed_at')))
            ->orderBy('created_at')->get();

        $publicaties = Publication::whereIn('collaboration_id', Collaboration::where('company_id', $bedrijf->id)->select('id'))
            ->where('posted_at', '>=', $van)->where('posted_at', '<', $tot)->orderBy('posted_at')->get();
        $perPublicatie = $this->cijfers->perPublicatie($publicaties);
        $titels = Collaboration::with('creator.user')->whereIn('id', $publicaties->pluck('collaboration_id'))->get()->keyBy('id');
        $perPublicatie = array_map(fn ($p) => $p + [
            'title' => $titels[$p['collaboration_id']]->title ?? '', 'creator' => $titels[$p['collaboration_id']]->creator?->user->name,
        ], $perPublicatie);
        usort($perPublicatie, fn ($a, $b) => ($b['metrics']['views']['value'] ?? -1) <=> ($a['metrics']['views']['value'] ?? -1));

        $boekingen = CreditLedger::where('company_id', $bedrijf->id)->where('created_at', '>=', $van)->where('created_at', '<', $tot)->get();
        $verbruikt = CreditLedger::query()->join('collaborations', 'collaborations.id', '=', 'credit_ledger.collaboration_id')
            ->where('credit_ledger.company_id', $bedrijf->id)->where('credit_ledger.kind', CreditLedger::VERBRUIKEN)
            ->where('credit_ledger.created_at', '>=', $van)->where('credit_ledger.created_at', '<', $tot)->sum('collaborations.points');
        $eindeMaand = $tot->isFuture() ? now() : $tot;

        return [
            'maand' => $van->format('Y-m'),
            'maand_label' => $van->translatedFormat('F Y'),
            'lopend' => $tot->isFuture(),
            'bedrijf' => ['id' => $bedrijf->id, 'name' => $bedrijf->name],
            'pakket' => $bedrijf->subscriptions()->with('planVersion.plan')->latest('starts_on')->first()?->planVersion->plan->name,
            'tellingen' => [
                'aangevraagd' => $samenwerkingen->filter(fn ($c) => $c->created_at->gte($van) && $c->created_at->lt($tot))->count(),
                'live' => $samenwerkingen->filter(fn ($c) => $c->live_at?->gte($van) && $c->live_at->lt($tot))->count(),
                'afgerond' => $samenwerkingen->filter(fn ($c) => $c->completed_at?->gte($van) && $c->completed_at->lt($tot))->count(),
                'creators' => $samenwerkingen->pluck('creator_id')->filter()->unique()->count(),
            ],
            'samenwerkingen' => $samenwerkingen->map(fn ($c) => SamenwerkingPresenter::kort($c) + ['live_at' => $c->live_at?->toIso8601String()])->values(),
            'publicaties' => $perPublicatie,
            'platforms' => Cijfers::perPlatform($perPublicatie),
            'tegoed' => [
                'toegekend' => (int) $boekingen->where('kind', CreditLedger::TOEKENNEN)->sum('points'),
                'gereserveerd' => (int) -$boekingen->where('kind', CreditLedger::RESERVEREN)->sum('points'),
                'verbruikt' => (int) $verbruikt,
                'vrijgegeven' => (int) $boekingen->where('kind', CreditLedger::VRIJGEVEN)->sum('points'),
                'correcties' => (int) $boekingen->where('kind', CreditLedger::CORRECTIE)->sum('points'),
                'saldo_einde' => (int) CreditLedger::where('company_id', $bedrijf->id)->where('created_at', '<', $eindeMaand)->sum('points'),
                'boekingen' => $boekingen->sortBy('id')->map(fn ($b) => ['at' => $b->created_at->toIso8601String(), 'kind' => $b->kind, 'points' => $b->points, 'reason' => $b->reason])->values(),
            ],
            'ongebruikt_tegoed' => config('geld.unused_credit'),
        ];
    }
}
