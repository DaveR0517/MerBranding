<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payout;
use App\Models\PayoutRun;
use App\Services\Geld;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

/** Financieel (AdminFinancieel): betaalruns voor creators en facturen voor ondernemers. */
class FinancieelController extends Controller
{
    public function __construct(private Geld $geld) {}

    public function index(Request $request): Response
    {
        Gate::authorize('financieel.bekijken');
        $vrijdag = Geld::vrijdag();
        $maand = CarbonImmutable::now()->startOfMonth();

        $runs = PayoutRun::latest('run_on')->limit(12)->get();
        $inRun = Payout::with('creator.user', 'collaboration.company')->whereIn('payout_run_id', $runs->pluck('id'))->get()->groupBy('payout_run_id');
        $vrij = Payout::with('creator.user', 'collaboration.company')->where('status', 'opeisbaar')->whereNull('payout_run_id')->orderBy('run_on')->get();
        $facturen = Invoice::with('company')->latest('issued_on')->latest('id')->limit(200)->get();

        return Inertia::render('team/cijfers/financieel', [
            'can_manage' => $request->user()->can('financieel.beheren'),
            'next_friday' => $vrijdag->toDateString(),
            'totals' => [
                'opeisbaar' => (int) Payout::where('status', 'opeisbaar')->sum('amount_cents'),
                'opeisbaar_creators' => Payout::where('status', 'opeisbaar')->distinct('creator_id')->count('creator_id'),
                'uitbetaald_maand' => (int) Payout::where('status', 'uitbetaald')->where('paid_at', '>=', $maand)->sum('amount_cents'),
                'openstaand' => (int) Invoice::where('status', Invoice::OPENSTAAND)->sum('total_cents'),
                'openstaand_aantal' => Invoice::where('status', Invoice::OPENSTAAND)->count(),
                'te_laat_aantal' => Invoice::where('status', Invoice::OPENSTAAND)->whereDate('due_on', '<', today())->count(),
                'gefactureerd_maand' => (int) Invoice::whereDate('period_start', $maand->toDateString())->sum('subtotal_cents'),
            ],
            'runs' => $runs->map(fn (PayoutRun $r) => [
                'id' => $r->id, 'run_on' => $r->run_on->toDateString(), 'status' => $r->status, 'total_cents' => $r->total_cents,
                'exported_at' => $r->exported_at?->toIso8601String(), 'paid_at' => $r->paid_at?->toIso8601String(),
                'payouts' => ($inRun[$r->id] ?? collect())->map(fn ($p) => $this->payout($p))->values(),
            ])->values(),
            'claimable' => $vrij->map(fn ($p) => $this->payout($p))->values(),
            'invoices' => $facturen->map(fn (Invoice $f) => [
                'id' => $f->id, 'number' => $f->number, 'company' => $f->company->name, 'description' => $f->lines[0]['omschrijving'] ?? '',
                'issued_on' => $f->issued_on->toDateString(), 'due_on' => $f->due_on->toDateString(), 'paid_at' => $f->paid_at?->toIso8601String(),
                'total_cents' => $f->total_cents, 'subtotal_cents' => $f->subtotal_cents, 'status' => $f->weergaveStatus(), 'reminders_sent' => $f->reminders_sent,
            ])->values(),
            'settings' => [
                'unused_credit' => config('geld.unused_credit'),
                'unused_credit_is_proposal' => (bool) config('geld.unused_credit_is_proposal'),
                'vat_rate' => config('geld.vat_rate'), 'payment_days' => config('geld.payment_days'), 'reminder_days' => config('geld.reminder_days'),
            ],
        ]);
    }

    public function samenstellen(Request $request): RedirectResponse
    {
        Gate::authorize('financieel.beheren');
        $data = $request->validate(['run_on' => ['nullable', 'date']]);
        $run = $this->geld->samenstellen(isset($data['run_on']) ? CarbonImmutable::parse($data['run_on']) : Geld::vrijdag(), $request->user());

        return back()->with('melding', 'Betaalrun van '.$run->run_on->translatedFormat('l j F').' samengesteld: '.Geld::euro($run->total_cents).'.');
    }

    public function uitRun(Request $request, Payout $payout): RedirectResponse
    {
        Gate::authorize('financieel.beheren');
        $this->geld->uitRun($payout, $request->user());

        return back()->with('melding', 'Vergoeding uit de betaalrun gehaald. Hij blijft opeisbaar.');
    }

    public function export(PayoutRun $run)
    {
        Gate::authorize('financieel.beheren');
        $csv = $this->geld->export($run);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="betaalrun-'.$run->run_on->toDateString().'.csv"',
        ]);
    }

    public function uitbetaald(Request $request, PayoutRun $run): RedirectResponse
    {
        Gate::authorize('financieel.beheren');
        $this->geld->markeerUitbetaald($run, $request->user());

        return back()->with('melding', 'Betaalrun gemarkeerd als uitbetaald. De creators hebben een melding gekregen.');
    }

    public function factuur(Invoice $factuur): View
    {
        Gate::authorize('financieel.bekijken');

        return view('geld.factuur', ['f' => $factuur->load('company.owners'), 'terug' => route('team.financieel')]);
    }

    public function betaald(Request $request, Invoice $factuur): RedirectResponse
    {
        Gate::authorize('financieel.beheren');
        $this->geld->markeerBetaald($factuur, $request->user());

        return back()->with('melding', "Factuur {$factuur->number} staat op betaald.");
    }

    /** Handmatig de maandelijkse toekenning en facturen draaien (normaal automatisch op de 1e). Veilig om vaker te doen. */
    public function maandfacturen(Request $request): RedirectResponse
    {
        Gate::authorize('financieel.beheren');
        $aantal = $this->geld->maandelijks();

        return back()->with('melding', $aantal === 0 ? 'Alle facturen van deze maand bestonden al.' : "{$aantal} facturen gemaakt en tegoed toegekend.");
    }

    private function payout(Payout $p): array
    {
        return [
            'id' => $p->id, 'creator' => $p->creator->user->name, 'company' => $p->collaboration->company->name,
            'collaboration' => $p->collaboration->title, 'collaboration_id' => $p->collaboration_id, 'number' => $p->collaboration->number,
            'amount_cents' => $p->amount_cents, 'run_on' => $p->run_on->toDateString(), 'status' => $p->status,
            'vat_status' => $p->creator->vat_status, 'has_details' => filled($p->creator->payout_details),
            'live_at' => $p->collaboration->live_at?->toIso8601String(),
        ];
    }
}
