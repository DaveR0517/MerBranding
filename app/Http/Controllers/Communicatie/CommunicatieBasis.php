<?php

namespace App\Http\Controllers\Communicatie;

use App\Http\Controllers\Controller;
use App\Http\Presenters\CommunicatiePresenter as Weergave;
use App\Models\Collaboration;
use App\Models\HelpArtikel;
use App\Models\SupportVraag;
use App\Models\Uitnodigingslink;
use App\Models\User;
use App\Services\Berichten;
use App\Services\Support;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gedeelde werking van berichten, help en support voor elke omgeving. Elke omgeving heeft een eigen controller
 * die zegt welke samenwerkingen de gebruiker mag zien en waar het samenwerkingsscherm staat.
 */
abstract class CommunicatieBasis extends Controller
{
    /** team, ondernemer of creator */
    abstract protected function omgeving(): string;

    /** Samenwerkingen waarover deze gebruiker berichten mag lezen en schrijven. */
    abstract protected function samenwerkingen(User $user): Builder;

    abstract protected function samenwerkingUrl(Collaboration $c): string;

    /** Samenwerkingen in de inbox; het team ziet alleen gesprekken die al begonnen zijn. */
    protected function inboxSamenwerkingen(User $user): Builder
    {
        return $this->samenwerkingen($user);
    }

    public function __construct(protected Berichten $berichten, protected Support $support) {}

    public function inbox(Request $request): Response
    {
        return $this->berichtenScherm($request, null);
    }

    public function gesprek(Request $request, Collaboration $samenwerking): Response
    {
        $this->magZien($request->user(), $samenwerking);
        $this->berichten->gelezen($samenwerking, $request->user());

        return $this->berichtenScherm($request, $samenwerking);
    }

    public function sturen(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        $this->magZien($request->user(), $samenwerking);
        $data = $request->validate([
            'channel' => ['required', Rule::in(Berichten::kanalen($this->omgeving()))],
            'body' => ['required', 'string', 'max:4000'],
        ], ['channel.in' => 'Dit gesprek is niet beschikbaar.', 'body.required' => 'Schrijf eerst een bericht.']);

        $this->berichten->sturen($samenwerking, $request->user(), $this->omgeving(), $data['channel'], trim($data['body']));

        return back();
    }

    public function help(Request $request): Response
    {
        $zoek = (string) $request->query('zoek', '');
        $user = $request->user();

        return Inertia::render("{$this->omgeving()}/communicatie/help", [
            'zoek' => $zoek,
            'articles' => HelpArtikel::voor($this->omgeving())->zoek($zoek)->get()->map(fn ($a) => Weergave::artikel($a))->values(),
            'tickets' => SupportVraag::where('user_id', $user->id)->latest('updated_at')->limit(30)->get()->map(fn ($v) => Weergave::vraagKort($v))->values(),
            'collaborations' => $this->samenwerkingen($user)->latest()->limit(50)->get(['id', 'number', 'title'])->values(),
            'priorities' => SupportVraag::PRIORITEITEN,
            'termijn' => 'Eerste reactie binnen '.config('mebranding.support.reactie_werkdagen', 1).' werkdag (voorstel).',
        ]);
    }

    public function vraagStellen(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(array_keys(SupportVraag::PRIORITEITEN))],
            'collaboration_id' => ['nullable', 'integer'],
        ], ['subject.required' => 'Geef je vraag een onderwerp.', 'body.required' => 'Beschrijf je vraag.']);

        if (! empty($data['collaboration_id'])) {
            abort_unless($this->samenwerkingen($request->user())->whereKey($data['collaboration_id'])->exists(), 403);
        }
        $vraag = $this->support->indienen($request->user(), $this->omgeving(), $data);

        return to_route("{$this->omgeving()}.communicatie.help.vraag", $vraag)
            ->with('melding', "Je vraag is verstuurd. Nummer {$vraag->number}. We reageren uiterlijk ".Support::termijn($vraag->created_at)->translatedFormat('l j F').'.');
    }

    public function vraag(Request $request, SupportVraag $vraag): Response
    {
        abort_unless($vraag->user_id === $request->user()->id, 403);

        return Inertia::render("{$this->omgeving()}/communicatie/vraag", ['ticket' => Weergave::vraag($vraag, false)]);
    }

    public function reageren(Request $request, SupportVraag $vraag): RedirectResponse
    {
        abort_unless($vraag->user_id === $request->user()->id, 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']], ['body.required' => 'Schrijf eerst een reactie.']);
        $this->support->reageren($vraag, $request->user(), $data['body']);

        return back()->with('melding', 'Je reactie is verstuurd.');
    }

    public function uitnodigen(Request $request): Response
    {
        $link = Uitnodigingslink::voor($request->user());
        $aanmeldingen = $link->aanmeldingen();

        return Inertia::render("{$this->omgeving()}/communicatie/uitnodigen", [
            'link' => route('publiek.communicatie.uitnodiging', $link->code),
            'code' => $link->code,
            // Klant geworden telt alleen bij ondernemers; voor creators volgt de screening (andere module) de goedkeuring.
            'stats' => ['aangemeld' => (clone $aanmeldingen)->count(), 'klant' => $this->omgeving() === 'ondernemer' ? (clone $aanmeldingen)->where('status', 'klant')->count() : null],
        ]);
    }

    protected function magZien(User $user, Collaboration $c): void
    {
        abort_unless($this->samenwerkingen($user)->whereKey($c->id)->exists(), 403);
    }

    private function berichtenScherm(Request $request, ?Collaboration $gekozen): Response
    {
        $user = $request->user();
        $lijst = $this->inboxSamenwerkingen($user)->when($gekozen, fn ($q) => $q->orWhere('id', $gekozen->id))->with(['company', 'location', 'creator.user'])->latest('updated_at')->limit(150)->get();

        return Inertia::render("{$this->omgeving()}/communicatie/berichten", [
            'conversations' => Weergave::inbox($lijst, $user, $this->omgeving(), $this->berichten),
            'conversation' => $gekozen ? Weergave::gesprek($gekozen, $user, $this->omgeving(), $this->berichten, $this->samenwerkingUrl($gekozen)) : null,
            'shared_channel' => Berichten::gezamenlijkAan(),
        ]);
    }
}
