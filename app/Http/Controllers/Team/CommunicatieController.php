<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Communicatie\CommunicatieBasis;
use App\Http\Presenters\CommunicatiePresenter as Weergave;
use App\Models\Bericht;
use App\Models\Collaboration;
use App\Models\HelpArtikel;
use App\Models\SupportVraag;
use App\Models\User;
use App\Services\Support;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Berichten met ondernemers en creators, de werkbak Support en het beheer van het helpcentrum. */
class CommunicatieController extends CommunicatieBasis
{
    protected function omgeving(): string
    {
        return 'team';
    }

    protected function samenwerkingen(User $user): Builder
    {
        return Collaboration::query();
    }

    protected function inboxSamenwerkingen(User $user): Builder
    {
        return Collaboration::query()->whereIn('id', Bericht::select('collaboration_id'));
    }

    protected function samenwerkingUrl(Collaboration $c): string
    {
        return route('team.samenwerking', $c);
    }

    protected function magZien(User $user, Collaboration $c): void
    {
        Gate::authorize('samenwerkingen.bekijken');
    }

    public function inbox(Request $request): Response
    {
        Gate::authorize('samenwerkingen.bekijken');

        return parent::inbox($request);
    }

    /** Helpcentrum zoals het team het ziet; met support.beheren ook de artikelen van alle doelgroepen om te bewerken. */
    public function help(Request $request): Response
    {
        if (! $request->user()->can(Support::RECHT)) {
            return parent::help($request);
        }
        $zoek = (string) $request->query('zoek', '');

        return Inertia::render('team/communicatie/help', [
            'zoek' => $zoek,
            'articles' => HelpArtikel::zoek($zoek)->orderBy('position')->orderBy('title')->get()->map(fn ($a) => Weergave::artikel($a, true))->values(),
            'tickets' => [],
            'collaborations' => [],
            'priorities' => SupportVraag::PRIORITEITEN,
            'termijn' => '',
            'beheer' => ['audiences' => HelpArtikel::DOELGROEPEN],
        ]);
    }

    public function artikelOpslaan(Request $request, ?HelpArtikel $artikel = null): RedirectResponse
    {
        Gate::authorize(Support::RECHT);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:20000'],
            'audiences' => ['required', 'array', 'min:1'],
            'audiences.*' => [Rule::in(HelpArtikel::DOELGROEPEN)],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], ['audiences.required' => 'Kies minstens één doelgroep.']);
        $data['position'] ??= 0;

        if ($artikel) {
            $artikel->update($data);
        } else {
            $slug = Str::slug($data['title']);
            HelpArtikel::create($data + ['slug' => HelpArtikel::where('slug', $slug)->exists() ? $slug.'-'.Str::lower(Str::random(4)) : $slug]);
        }

        return back()->with('melding', 'Het artikel is opgeslagen.');
    }

    public function artikelVerwijderen(HelpArtikel $artikel): RedirectResponse
    {
        Gate::authorize(Support::RECHT);
        $artikel->delete();

        return back()->with('melding', 'Het artikel is verwijderd.');
    }

    /** Werkbak Support: lijst met filters en de gekozen vraag. */
    public function supportLijst(Request $request, ?SupportVraag $vraag = null): Response
    {
        Gate::authorize(Support::RECHT);
        $filter = (string) $request->query('filter', 'open');
        $zoek = trim((string) $request->query('zoek', ''));
        $user = $request->user();

        $basis = SupportVraag::query()->when($zoek !== '', fn ($q) => $q->where(fn ($q) => $q->where('subject', 'like', "%{$zoek}%")->orWhere('number', 'like', "%{$zoek}%")));
        $tellers = [
            'open' => (clone $basis)->where('status', 'open')->count(),
            'mijn' => (clone $basis)->where('status', '!=', 'gesloten')->where('assigned_to', $user->id)->count(),
            'ondernemer' => (clone $basis)->where('status', '!=', 'gesloten')->where('portal', 'ondernemer')->count(),
            'creator' => (clone $basis)->where('status', '!=', 'gesloten')->where('portal', 'creator')->count(),
            'beantwoord' => (clone $basis)->where('status', 'beantwoord')->count(),
            'gesloten' => (clone $basis)->where('status', 'gesloten')->count(),
            'alles' => (clone $basis)->count(),
        ];
        $lijst = match ($filter) {
            'mijn' => $basis->where('status', '!=', 'gesloten')->where('assigned_to', $user->id),
            'ondernemer', 'creator' => $basis->where('status', '!=', 'gesloten')->where('portal', $filter),
            'beantwoord', 'gesloten' => $basis->where('status', $filter),
            'alles' => $basis,
            default => $basis->where('status', 'open'),
        };

        return Inertia::render('team/communicatie/support', [
            'filter' => $filter,
            'zoek' => $zoek,
            'counts' => $tellers,
            'tickets' => $lijst->with(['user', 'company', 'assignee'])->orderByRaw("case priority when 'hoog' then 0 when 'normaal' then 1 else 2 end")->latest('updated_at')->limit(200)->get()
                ->map(fn ($v) => Weergave::vraagKort($v))->values(),
            'ticket' => $vraag ? Weergave::vraag($vraag, true) + ['respond_before' => Support::termijn($vraag->created_at)->toIso8601String()] : null,
            'statuses' => SupportVraag::STATUSSEN,
            'priorities' => SupportVraag::PRIORITEITEN,
            'team' => $this->support->medewerkers()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
        ]);
    }

    public function supportAntwoorden(Request $request, SupportVraag $vraag): RedirectResponse
    {
        Gate::authorize(Support::RECHT);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'internal' => ['boolean']], ['body.required' => 'Schrijf eerst een antwoord.']);
        $this->support->antwoorden($vraag, $request->user(), $data['body'], (bool) ($data['internal'] ?? false));

        return back()->with('melding', ($data['internal'] ?? false) ? 'De notitie is opgeslagen.' : 'Je antwoord is verstuurd.');
    }

    public function supportBijwerken(Request $request, SupportVraag $vraag): RedirectResponse
    {
        Gate::authorize(Support::RECHT);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(SupportVraag::STATUSSEN))],
            'priority' => ['required', Rule::in(array_keys(SupportVraag::PRIORITEITEN))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $this->support->status($vraag, $request->user(), $data['status'], $data['priority'], $data['assigned_to'] ?? null);

        return back()->with('melding', 'De vraag is bijgewerkt.');
    }
}
