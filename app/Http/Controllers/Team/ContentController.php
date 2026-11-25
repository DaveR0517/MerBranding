<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Presenters\ContentPresenter;
use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Company;
use App\Models\ContentNote;
use App\Models\Creator;
use App\Models\Location;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Status;
use App\Models\Task;
use App\Models\UsageRight;
use App\Models\VideoIdea;
use App\Services\Contentkalender;
use App\Services\Koppelingen\Koppelingen;
use App\Services\Melder;
use App\Services\Samenwerking;
use App\Services\Werkvoorraad;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Team: monitor koppelingen (AdminApi), planning (AdminPlanning), content per bedrijf en Radar (placeholder). */
class ContentController extends Controller
{
    /* ----------------------------------------------------------------- Koppelingen en controle post (W8) */

    public function koppelingen(Koppelingen $k): Response
    {
        Gate::authorize('koppelingen.beheren');

        $accounts = SocialAccount::with('owner')->orderBy('expires_at')->get()->map(function (SocialAccount $a) {
            return [
                'id' => $a->id,
                'platform' => $a->platform,
                'handle' => $a->handle,
                'eigenaar' => Koppelingen::eigenaarNaam($a->owner),
                'soort' => $a->owner_type === (new Company)->getMorphClass() ? 'ondernemer' : 'creator',
                'status' => $a->status,
                'verloopt_op' => $a->expires_at?->toIso8601String(),
                'laatste_sync' => $a->last_synced_at?->toIso8601String(),
                'fout' => $a->last_error,
                'toestemming_versie' => $a->consent_version,
                'toestemming_op' => $a->consent_at?->toIso8601String(),
                'waarschuwing' => $a->warning(),
            ];
        });

        $publicaties = Publication::query()
            ->whereIn('collaboration_id', Collaboration::where('status', Status::Live->value)->select('id'))
            ->latest('posted_at')->get();
        $controles = ActivityLog::with('user')->where('subject_type', (new Publication)->getMorphClass())
            ->whereIn('subject_id', $publicaties->pluck('id'))->latest('id')->get()->groupBy('subject_id');
        $samenwerkingen = Collaboration::with(['company', 'creator.user'])->whereIn('id', $publicaties->pluck('collaboration_id'))->get()->keyBy('id');

        return Inertia::render('team/content/koppelingen', [
            'accounts' => $accounts->values(),
            'platforms' => collect($k->sleutels())->map(fn ($s) => ['platform' => $s, 'naam' => $k->platform($s)->naam(), 'ingesteld' => $k->platform($s)->isIngesteld(), 'token_dagen' => $k->platform($s)->tokenDagen()])->values(),
            'warn_days' => (int) config('content.koppelingen.warn_days'),
            'publications' => $publicaties->map(function (Publication $p) use ($controles, $samenwerkingen) {
                $c = $samenwerkingen[$p->collaboration_id];

                return [
                    'id' => $p->id, 'platform' => $p->platform, 'url' => $p->url,
                    'posted_at' => $p->posted_at->toIso8601String(), 'last_found_at' => $p->last_found_at?->toIso8601String(),
                    'collaboration' => ['id' => $c->id, 'title' => $c->title, 'company' => $c->company->name, 'creator' => $c->creator?->user->name, 'measure_until' => $c->measure_until?->toIso8601String()],
                    'checks' => ($controles[$p->id] ?? collect())->take(3)->map(fn ($l) => ['found' => $l->event === 'post_gevonden', 'by' => $l->user?->name, 'at' => $l->created_at->toIso8601String(), 'note' => $l->reason])->values(),
                ];
            })->values(),
        ]);
    }

    /** Handmatige controle "post verwijderd" (W8): gevonden of niet gevonden, met logregel. Geen strafpunten (open punt 5). */
    public function postControle(Request $request, Publication $publicatie, Melder $melder, Samenwerking $keten): RedirectResponse
    {
        Gate::authorize('koppelingen.beheren');
        $data = $request->validate([
            'found' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
            'melden' => ['nullable', 'boolean'],
        ]);
        $gevonden = (bool) $data['found'];

        if ($gevonden) {
            $publicatie->update(['last_found_at' => now()]);
        }
        ActivityLog::create([
            'user_id' => $request->user()->id, 'subject_type' => $publicatie->getMorphClass(), 'subject_id' => $publicatie->id,
            'event' => $gevonden ? 'post_gevonden' : 'post_niet_gevonden', 'reason' => $data['note'] ?? null,
            'data' => ['collaboration_id' => $publicatie->collaboration_id, 'url' => $publicatie->url],
        ]);

        $c = Collaboration::with(['company', 'creator.user'])->find($publicatie->collaboration_id);
        // Melden (W8): de creator krijgt 24 uur, contentcontrole krijgt het signaal. Eén keer per publicatie.
        if (! $gevonden && ($data['melden'] ?? false) && $c->creator && $c->markeerHerinnering("post-weg-{$publicatie->id}")) {
            $melder->stuur('c-post-weg', $c->creator->user, $keten->vars($c), $keten->urlCreator($c));
            $melder->stuur('t-post-weg', $melder->team('content.controleren'), $keten->vars($c), $keten->urlTeam($c));
        }

        return back()->with('melding', $gevonden ? 'Vastgelegd: de post is gevonden.' : 'Vastgelegd: de post is niet gevonden.');
    }

    /* ----------------------------------------------------------------- Planning (AdminPlanning) */

    public function planning(Request $request): Response
    {
        Gate::authorize('planning.beheren');
        $filters = $request->validate([
            'weergave' => ['nullable', 'in:dag,week'],
            'datum' => ['nullable', 'date_format:Y-m-d'],
            'vestiging' => ['nullable', 'integer'],
            'creator' => ['nullable', 'integer'],
        ]);
        $weergave = $filters['weergave'] ?? 'week';
        $dag = isset($filters['datum']) ? CarbonImmutable::parse($filters['datum']) : CarbonImmutable::today();
        [$van, $tot] = $weergave === 'dag' ? [$dag->startOfDay(), $dag->endOfDay()] : [$dag->startOfWeek(), $dag->endOfWeek()];

        $basis = fn () => Collaboration::with(['company', 'location', 'creator.user'])
            ->when($filters['vestiging'] ?? null, fn ($q, $v) => $q->where('location_id', $v))
            ->when($filters['creator'] ?? null, fn ($q, $v) => $q->where('creator_id', $v));

        $bezoeken = $basis()->whereBetween('visit_at', [$van, $tot])
            ->whereNotIn('status', [Status::Geannuleerd->value])->orderBy('visit_at')->get();

        // Zonder bevestiging: wacht op een tijdvak van de creator (3), of wacht op de ondernemer bij "zelf bevestigen" (4, 24 uur).
        $wachtTijdvak = $basis()->where('status', Status::CreatorGevonden->value)->oldest('updated_at')->get();
        $wachtOndernemer = $basis()->where('status', Status::BezoekGepland->value)
            ->whereHas('company', fn ($q) => $q->where('self_confirm_visits', true))
            ->get()
            ->map(fn (Collaboration $c) => $c->setAttribute('vast_op', $c->bezoekVastOp()))
            ->filter(fn ($c) => $c->vast_op?->isFuture())->values();

        $bezoek = fn (Collaboration $c, string $wacht) => [
            'id' => $c->id, 'number' => $c->number, 'title' => $c->title, 'status' => $c->status->label(), 'status_nr' => $c->status->number(),
            'company' => $c->company->name, 'location' => $c->location->name, 'city' => $c->location->city, 'address' => $c->location->address,
            'experience' => $c->location->experience, 'creator' => $c->creator?->user->name, 'level' => $c->creator?->levelName(),
            'visit_at' => $c->visit_at?->toIso8601String(), 'wacht' => $wacht,
            'vast_op' => $c->getAttribute('vast_op')?->toIso8601String(),
        ];

        return Inertia::render('team/content/planning', [
            'filters' => ['weergave' => $weergave, 'datum' => $dag->toDateString(), 'vestiging' => $filters['vestiging'] ?? null, 'creator' => $filters['creator'] ?? null],
            'periode' => ['van' => $van->toDateString(), 'tot' => $tot->toDateString(), 'week' => $van->isoWeek()],
            'visits' => $bezoeken->map(fn ($c) => $bezoek($c, $c->status === Status::BezoekGepland && $c->company->self_confirm_visits ? 'ondernemer' : 'geen'))->values(),
            'unconfirmed' => [...$wachtTijdvak->map(fn ($c) => $bezoek($c, 'creator')), ...$wachtOndernemer->map(fn ($c) => $bezoek($c, 'ondernemer'))],
            'locations' => Location::with('company')->orderBy('name')->get()->map(fn ($l) => ['id' => $l->id, 'name' => "{$l->company->name}, {$l->name}"])->values(),
            'creators' => Creator::with('user')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->user->name])->sortBy('name')->values(),
        ]);
    }

    /* ----------------------------------------------------------------- Content per bedrijf: kalender, ideeën, rechten */

    public function bedrijf(Request $request, Contentkalender $kalender, ?Company $bedrijf = null): Response
    {
        Gate::authorize('bedrijven.bekijken');
        $bedrijven = Company::orderBy('name')->get(['id', 'name']);
        $bedrijf ??= $bedrijven->first();

        return Inertia::render('team/content/bedrijf', [
            'companies' => $bedrijven,
            'company' => $bedrijf ? ['id' => $bedrijf->id, 'name' => $bedrijf->name] : null,
            'kalender' => $bedrijf ? $kalender->voor($bedrijf, Contentkalender::maand($request->query('maand'))) : null,
            'ideas' => $bedrijf ? VideoIdea::with('collaboration')->where('company_id', $bedrijf->id)->latest()->get()->map(fn ($i) => ContentPresenter::idee($i))->values() : [],
            'rights' => $bedrijf ? UsageRight::with('collaboration.creator.user')->voorBedrijf($bedrijf->id)->orderBy('ends_on')->get()->map(fn ($r) => ContentPresenter::recht($r))->values() : [],
            'collaborations' => $bedrijf ? Collaboration::where('company_id', $bedrijf->id)->latest()->get(['id', 'number', 'title', 'status'])->map(fn ($c) => ['id' => $c->id, 'label' => "{$c->number} {$c->title} ({$c->status->label()})"])->values() : [],
            'standaard' => ContentPresenter::standaardRechten(),
            'can' => ['beheren' => Gate::allows('bedrijven.beheren')],
        ]);
    }

    public function notitieOpslaan(Request $request, Company $bedrijf): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        ContentNote::create(ContentPresenter::valideerNotitie($request) + ['company_id' => $bedrijf->id, 'user_id' => $request->user()->id]);

        return back()->with('melding', 'De notitie staat in de kalender.');
    }

    public function notitieVerwijderen(ContentNote $notitie): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        $notitie->delete();

        return back()->with('melding', 'De notitie is verwijderd.');
    }

    public function ideeOpslaan(Request $request, Company $bedrijf): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        VideoIdea::create(ContentPresenter::valideerIdee($request) + ['company_id' => $bedrijf->id, 'user_id' => $request->user()->id, 'source' => 'team', 'status' => 'idee']);

        return back()->with('melding', 'Het idee is toegevoegd.');
    }

    /** Status wijzigen of bij een aanvraag naar het idee verwijzen (dan wordt het gepland). */
    public function ideeBijwerken(Request $request, VideoIdea $idee): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        $data = $request->validate([
            'status' => ['required', Rule::in(VideoIdea::STATUSSEN)],
            'collaboration_id' => ['nullable', 'integer', Rule::exists('collaborations', 'id')->where('company_id', $idee->company_id)],
        ]);
        if (! empty($data['collaboration_id']) && $data['status'] === 'idee') {
            $data['status'] = 'gepland';
        }
        $idee->update($data);

        return back()->with('melding', 'Het idee is bijgewerkt.');
    }

    public function rechtOpslaan(Request $request, Company $bedrijf): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        $data = $request->validate([
            'collaboration_id' => ['required', 'integer', Rule::exists('collaborations', 'id')->where('company_id', $bedrijf->id)],
            'kind' => ['required', Rule::in(UsageRight::SOORTEN)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'ad_code' => ['nullable', 'string', 'max:120'],
        ]);
        UsageRight::create($data + ['is_proposal' => false, 'status' => 'actief']);

        return back()->with('melding', 'Het gebruiksrecht is vastgelegd.');
    }

    public function rechtBijwerken(Request $request, UsageRight $recht, Werkvoorraad $werk): RedirectResponse
    {
        Gate::authorize('bedrijven.beheren');
        $data = $request->validate([
            'ends_on' => ['required', 'date', 'after_or_equal:'.$recht->starts_on->toDateString()],
            'ad_code' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(UsageRight::STATUSSEN)],
        ]);
        // Nieuwe einddatum: meldingen opnieuw, en de verlenging is afgehandeld.
        if ($data['ends_on'] !== $recht->ends_on->toDateString()) {
            $data += ['notified' => [], 'extension_requested_at' => null, 'is_proposal' => false];
            $werk->sluit($recht->collaboration, 'rechten_verlengen');
        }
        $recht->update($data);

        return back()->with('melding', 'Het gebruiksrecht is bijgewerkt.');
    }

    /** Fase 7, alleen na akkoord: placeholder met de bedrijven die interesse toonden. */
    public function radar(): Response
    {
        return Inertia::render('team/content/radar', [
            'interest' => Task::with('company')->open()->where('type', 'radar_interesse')->latest()->get()
                ->map(fn ($t) => ['id' => $t->id, 'company' => $t->company?->name, 'at' => $t->created_at->toIso8601String()])->values(),
        ]);
    }
}
