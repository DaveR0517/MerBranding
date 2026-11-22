<?php

namespace App\Http\Controllers\Team\Beheer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pakketten en prijsversies (CLAUDE.md 7.2). Een prijswijziging is een nieuwe versie met een ingangsdatum in de toekomst:
 * die geldt alleen voor nieuwe perioden en nieuwe aanvragen. Lopende abonnementen houden hun versie. Alles VOORSTEL tot besloten.
 */
class PakkettenController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('instellingen.beheren');

        $klanten = Subscription::where('status', 'actief')->selectRaw('plan_version_id, count(*) as aantal')->groupBy('plan_version_id')->pluck('aantal', 'plan_version_id');

        return Inertia::render('team/beheer/pakketten', [
            'vandaag' => today()->toDateString(),
            'pakketten' => Plan::with(['versions' => fn ($q) => $q->orderBy('valid_from')])->orderBy('id')->get()->map(function (Plan $p) use ($klanten) {
                $huidig = $p->currentVersion();

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'klanten' => $p->versions->sum(fn ($v) => $klanten[$v->id] ?? 0),
                    'huidig_id' => $huidig?->id,
                    'versies' => $p->versions->map(fn (PlanVersion $v) => [
                        'id' => $v->id, 'nummer' => $p->versions->search(fn ($x) => $x->is($v)) + 1,
                        'price_cents' => $v->price_cents, 'points_per_month' => $v->points_per_month,
                        'valid_from' => $v->valid_from->toDateString(), 'is_proposal' => $v->is_proposal,
                        'status' => match (true) {
                            $v->is($huidig) => 'geldt',
                            $v->valid_from->isFuture() => 'gepland',
                            default => 'vervangen',
                        },
                        'klanten' => (int) ($klanten[$v->id] ?? 0),
                    ])->values(),
                ];
            })->values(),
        ]);
    }

    public function pakket(Request $request): RedirectResponse
    {
        Gate::authorize('instellingen.beheren');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('plans', 'name')],
            ...$this->regels('after_or_equal:today'),
        ], ['name.unique' => 'Er bestaat al een pakket met deze naam.'] + $this->meldingen());

        DB::transaction(function () use ($data, $request) {
            $plan = Plan::create(['name' => $data['name']]);
            $this->log($request, $plan, 'pakket_toegevoegd', $this->maakVersie($plan, $data));
        });

        return back()->with('melding', "Pakket {$data['name']} toegevoegd.");
    }

    public function versie(Request $request, Plan $plan): RedirectResponse
    {
        Gate::authorize('instellingen.beheren');
        $data = $request->validate($this->regels('after:today'), $this->meldingen());

        if ($plan->versions()->whereDate('valid_from', $data['valid_from'])->exists()) {
            throw ValidationException::withMessages(['valid_from' => 'Op deze datum begint al een versie van dit pakket.']);
        }

        $versie = $this->maakVersie($plan, $data);
        $this->log($request, $plan, 'prijsversie_toegevoegd', $versie);

        return back()->with('melding', "Nieuwe prijsversie voor {$plan->name} gaat in op {$versie->valid_from->translatedFormat('j F Y')}.");
    }

    public function intrekken(Request $request, PlanVersion $versie): RedirectResponse
    {
        Gate::authorize('instellingen.beheren');

        if (! $versie->valid_from->isFuture() || Subscription::where('plan_version_id', $versie->id)->exists()) {
            throw ValidationException::withMessages(['versie' => 'Alleen een geplande versie zonder klanten kun je intrekken.']);
        }

        $this->log($request, $versie->plan, 'prijsversie_ingetrokken', $versie);
        $versie->delete();

        return back()->with('melding', 'Geplande prijsversie ingetrokken.');
    }

    private function regels(string $datum): array
    {
        return [
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'points_per_month' => ['required', 'integer', 'min:0', 'max:1000'],
            'valid_from' => ['required', 'date', $datum],
            'is_proposal' => ['boolean'],
        ];
    }

    private function meldingen(): array
    {
        return [
            'valid_from.after' => 'Een nieuwe prijs geldt alleen voor nieuwe perioden: kies een ingangsdatum na vandaag.',
            'valid_from.after_or_equal' => 'Kies vandaag of een datum in de toekomst.',
        ];
    }

    /**
     * Slaat de ingangsdatum op als kale datum (JJJJ-MM-DD). Via de date-cast zou SQLite er "00:00:00" achter zetten,
     * waardoor Plan::currentVersion() (vergelijkt met de datum van vandaag) een versie die vandaag ingaat niet vindt.
     */
    private function maakVersie(Plan $plan, array $data): PlanVersion
    {
        $id = PlanVersion::query()->insertGetId([
            'plan_id' => $plan->id,
            'price_cents' => (int) round($data['price'] * 100),
            'points_per_month' => (int) $data['points_per_month'],
            'valid_from' => Carbon::parse($data['valid_from'])->toDateString(),
            'is_proposal' => (bool) ($data['is_proposal'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return PlanVersion::findOrFail($id);
    }

    private function log(Request $request, Plan $plan, string $event, PlanVersion $versie): void
    {
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'subject_type' => $plan->getMorphClass(),
            'subject_id' => $plan->id,
            'event' => $event,
            'reason' => "{$plan->name}: € ".number_format($versie->price_cents / 100, 2, ',', '.')." per maand, {$versie->points_per_month} punten, vanaf {$versie->valid_from->toDateString()}",
            'data' => $versie->only(['price_cents', 'points_per_month', 'is_proposal']) + ['valid_from' => $versie->valid_from->toDateString()],
        ]);
    }
}
