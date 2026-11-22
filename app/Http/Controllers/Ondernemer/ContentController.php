<?php

namespace App\Http\Controllers\Ondernemer;

use App\Http\Controllers\Concerns\KoppelingActies;
use App\Http\Controllers\Controller;
use App\Http\Presenters\ContentPresenter;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\ContentNote;
use App\Models\Task;
use App\Models\UsageRight;
use App\Models\VideoIdea;
use App\Services\Contentkalender;
use App\Services\Gebruiksrechten;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Ondernemer: contentkalender, video-ideeën, gebruiksrechten, koppelingen en Radar (placeholder). */
class ContentController extends Controller
{
    use KoppelingActies;

    public function kalender(Request $request, Contentkalender $kalender): Response
    {
        $bedrijf = $this->eigenaar($request);

        return Inertia::render('ondernemer/content/kalender', [
            'company' => ['name' => $bedrijf->name],
            'kalender' => $kalender->voor($bedrijf, Contentkalender::maand($request->query('maand'))),
        ]);
    }

    public function notitieOpslaan(Request $request): RedirectResponse
    {
        $data = ContentPresenter::valideerNotitie($request);
        ContentNote::create($data + ['company_id' => $this->eigenaar($request)->id, 'user_id' => $request->user()->id]);

        return back()->with('melding', 'Je notitie staat in de kalender.');
    }

    public function notitieVerwijderen(Request $request, ContentNote $notitie): RedirectResponse
    {
        abort_unless($notitie->company_id === $this->eigenaar($request)->id, 403);
        $notitie->delete();

        return back()->with('melding', 'De notitie is verwijderd.');
    }

    public function ideeen(Request $request): Response
    {
        $bedrijf = $this->eigenaar($request);

        return Inertia::render('ondernemer/content/ideeen', [
            'company' => ['name' => $bedrijf->name],
            'ideas' => VideoIdea::with('collaboration')->where('company_id', $bedrijf->id)->latest()->get()->map(fn ($i) => ContentPresenter::idee($i))->values(),
        ]);
    }

    public function ideeOpslaan(Request $request): RedirectResponse
    {
        $data = ContentPresenter::valideerIdee($request);
        VideoIdea::create($data + ['company_id' => $this->eigenaar($request)->id, 'user_id' => $request->user()->id, 'source' => 'ondernemer', 'status' => 'idee']);

        return back()->with('melding', 'Je idee is bewaard. Het team kan ernaar verwijzen bij een aanvraag.');
    }

    public function ideeVerwijderen(Request $request, VideoIdea $idee): RedirectResponse
    {
        abort_unless($idee->company_id === $this->eigenaar($request)->id && $idee->source === 'ondernemer' && $idee->status === 'idee', 403);
        $idee->delete();

        return back()->with('melding', 'Het idee is verwijderd.');
    }

    public function rechten(Request $request): Response
    {
        $bedrijf = $this->eigenaar($request);

        return Inertia::render('ondernemer/content/rechten', [
            'company' => ['name' => $bedrijf->name],
            'rights' => UsageRight::with('collaboration.creator.user')->voorBedrijf($bedrijf->id)->orderBy('ends_on')->get()->map(fn ($r) => ContentPresenter::recht($r))->values(),
            'standaard' => ContentPresenter::standaardRechten(),
            'mag_verlengen' => $this->magBeheren($request),
        ]);
    }

    public function verlengen(Request $request, UsageRight $recht, Gebruiksrechten $rechten): RedirectResponse
    {
        abort_unless($recht->collaboration->company_id === $this->eigenaar($request, true)->id, 403);
        $rechten->verlengen($recht, $request->user());

        return back()->with('melding', 'Je verzoek is naar je contactpersoon gestuurd. De prijs voor verlengen is nog niet bepaald.');
    }

    /** Fase 7, alleen na akkoord: placeholder. */
    public function zichtbaarheid(Request $request): Response
    {
        $bedrijf = $this->eigenaar($request);

        return Inertia::render('ondernemer/content/zichtbaarheid', [
            'company' => ['name' => $bedrijf->name],
            'interesse' => Task::open()->where('type', 'radar_interesse')->where('company_id', $bedrijf->id)->exists(),
        ]);
    }

    public function interesse(Request $request): RedirectResponse
    {
        $bedrijf = $this->eigenaar($request);
        $taak = Task::firstOrCreate(
            ['type' => 'radar_interesse', 'company_id' => $bedrijf->id, 'closed_at' => null],
            ['title' => "Interesse in Radar: {$bedrijf->name}", 'permission' => 'sales.beheren', 'due_at' => now()->addWeekdays(3)],
        );
        if ($taak->wasRecentlyCreated) {
            ActivityLog::create(['user_id' => $request->user()->id, 'subject_type' => $bedrijf->getMorphClass(), 'subject_id' => $bedrijf->id, 'event' => 'radar_interesse']);
        }

        return back()->with('melding', 'Dank je. Ons team neemt contact met je op over Radar.');
    }

    protected function eigenaar(Request $request, bool $beheren = false): Company
    {
        $bedrijf = $request->user()->companies()->firstOrFail();
        abort_if($beheren && ! $this->magBeheren($request), 403, 'Alleen de eigenaar kan dit doen.');

        return $bedrijf;
    }

    protected function omgeving(): string
    {
        return 'ondernemer';
    }

    /** Koppelen en verlengen: alleen de eigenaar (CLAUDE.md 5). */
    protected function magBeheren(Request $request): bool
    {
        return $request->user()->companies()->wherePivot('role', 'eigenaar')->exists();
    }
}
