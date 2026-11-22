<?php

namespace App\Http\Controllers\Team\Kanalen;

use App\Http\Controllers\Controller;
use App\Models\KanaalBericht;
use App\Models\KanaalGesprek;
use App\Models\SocialAccount;
use App\Services\Kanalen\EigenKanalen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Eigen kanalen, berichten: de Instagram-inbox van MEBRANDING. Antwoorden kan alleen binnen het 24-uursvenster van Meta. */
class BerichtenController extends Controller
{
    public function index(Request $request, EigenKanalen $k): Response
    {
        $account = $k->account('instagram');
        $gesprekken = $account->exists
            ? KanaalGesprek::where('social_account_id', $account->id)->with(['berichten' => fn ($q) => $q->latest('verzonden_op')->limit(1)])->orderByDesc('laatste_bericht_op')->limit(100)->get()
            : collect();
        $gekozen = $gesprekken->firstWhere('id', $request->integer('gesprek'));

        // Openen is lezen.
        if ($gekozen && $gekozen->ongelezen > 0) {
            $gekozen->update(['ongelezen' => 0]);
        }

        return Inertia::render('team/kanalen/berichten', [
            'gekoppeld' => (bool) $k->actief('instagram'),
            'mag_berichten' => (bool) $k->actief('instagram')?->heeftScope('instagram_business_manage_messages'),
            'venster_uren' => (int) config('kanalen.berichtvenster_uren'),
            'gesprekken' => $gesprekken->map(fn (KanaalGesprek $g) => [
                'id' => $g->id, 'naam' => $g->deelnemer_naam, 'laatste' => $g->berichten->first()?->tekst, 'op' => $g->laatste_bericht_op?->toIso8601String(),
                'ongelezen' => $g->ongelezen, 'antwoorden_tot' => $g->antwoordenTot()?->toIso8601String(),
            ])->values(),
            'gesprek' => $gekozen ? [
                'id' => $gekozen->id, 'naam' => $gekozen->deelnemer_naam, 'antwoorden_tot' => $gekozen->antwoordenTot()?->toIso8601String(),
                'berichten' => $gekozen->berichten()->with('user')->latest('verzonden_op')->limit(50)->get()->reverse()->map(fn (KanaalBericht $b) => [
                    'id' => $b->id, 'richting' => $b->richting, 'tekst' => $b->tekst, 'bijlagen' => $b->bijlagen, 'op' => $b->verzonden_op->toIso8601String(), 'door' => $b->user?->name,
                ])->values(),
            ] : null,
        ]);
    }

    public function sturen(Request $request, KanaalGesprek $gesprek, EigenKanalen $k): RedirectResponse
    {
        abort_unless($gesprek->account->owner_type === SocialAccount::EIGEN, 404);
        $tekst = $request->validate(['tekst' => ['required', 'string', 'max:1000']], ['tekst.max' => 'Een Instagram-bericht is hooguit 1000 tekens.'])['tekst'];
        $r = $k->stuurBericht($gesprek, $tekst, $request->user());

        return $r->isGelukt() ? back() : back()->withErrors(['tekst' => $r->melding]);
    }
}
