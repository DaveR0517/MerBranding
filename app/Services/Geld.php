<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Company;
use App\Models\CreditLedger;
use App\Models\Invoice;
use App\Models\Payout;
use App\Models\PayoutRun;
use App\Models\PlanVersion;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Geld (fase 6): betaalruns voor creators op vrijdag, maandelijks tegoed en facturen voor ondernemers, herinneringen.
 * Betaalpartij is nog niet gekozen: uitbetalen gaat via een export voor de bank, daarna handmatig markeren.
 */
class Geld
{
    public function __construct(private Melder $melder) {}

    public static function euro(int $centen): string
    {
        return '€ '.number_format($centen / 100, 2, ',', '.');
    }

    public static function vrijdag(?CarbonInterface $vanaf = null): CarbonImmutable
    {
        $dag = CarbonImmutable::parse($vanaf ?? today())->startOfDay();

        return $dag->isFriday() ? $dag : $dag->next(CarbonInterface::FRIDAY);
    }

    /* ----------------------------------------------------------------- Betaalrun (W7) */

    /** Opeisbare vergoedingen die wachten op uitbetaalgegevens van de creator: ze blijven staan voor de volgende run. */
    public function ingehouden(): Collection
    {
        return Payout::with('creator.user', 'collaboration.company')->where('status', 'opeisbaar')->whereNull('payout_run_id')->get()
            ->filter(fn (Payout $p) => blank($p->creator->payout_details))->values();
    }

    /**
     * Stelt de run van een vrijdag samen uit alle opeisbare vergoedingen tot en met die dag, behalve als de uitbetaalgegevens ontbreken.
     * Opnieuw samenstellen voegt nieuwe opeisbare vergoedingen toe.
     */
    public function samenstellen(CarbonInterface $vrijdag, ?User $door = null): PayoutRun
    {
        $vrijdag = CarbonImmutable::parse($vrijdag)->startOfDay();
        if (! $vrijdag->isFriday()) {
            throw ValidationException::withMessages(['run_on' => 'Een betaalrun is altijd op vrijdag.']);
        }

        return DB::transaction(function () use ($vrijdag, $door) {
            $run = PayoutRun::whereDate('run_on', $vrijdag->toDateString())->first()
                ?? PayoutRun::create(['run_on' => $vrijdag->toDateString(), 'status' => PayoutRun::SAMENGESTELD, 'composed_by' => $door?->id]);
            if ($run->status === PayoutRun::UITBETAALD) {
                throw ValidationException::withMessages(['run_on' => 'Deze betaalrun is al uitbetaald.']);
            }

            $ids = Payout::with('creator')->where('status', 'opeisbaar')->whereNull('payout_run_id')
                ->whereDate('run_on', '<=', $vrijdag->toDateString())->get()
                ->filter(fn (Payout $p) => filled($p->creator->payout_details))->pluck('id');

            Payout::whereIn('id', $ids)->update(['payout_run_id' => $run->id]);
            $run->update(['total_cents' => (int) $run->payouts()->sum('amount_cents')]);

            ActivityLog::create([
                'user_id' => $door?->id, 'subject_type' => $run->getMorphClass(), 'subject_id' => $run->id, 'event' => 'betaalrun_samengesteld',
                'data' => ['vrijdag' => $vrijdag->toDateString(), 'toegevoegd' => $ids->count(), 'totaal' => $run->total_cents],
            ]);

            return $run->fresh();
        });
    }

    /** Haalt een vergoeding uit een run die nog niet is uitbetaald (bijvoorbeeld bij twijfel over de gegevens). */
    public function uitRun(Payout $p, User $door): void
    {
        $run = PayoutRun::find($p->payout_run_id);
        abort_if(! $run || $run->status === PayoutRun::UITBETAALD, 422);
        Payout::whereKey($p->id)->update(['payout_run_id' => null]);
        $run->update(['total_cents' => (int) $run->payouts()->sum('amount_cents')]);
        $this->logSamenwerking($p->collaboration_id, $door, 'vergoeding_uit_betaalrun', null, ['betaalrun' => $run->run_on->toDateString()]);
    }

    /** CSV voor de bank (puntkomma, Nederlandse Excel). Betaalpartij nog niet gekoppeld. */
    public function export(PayoutRun $run): string
    {
        $regels = [['Creator', 'E-mail', 'Uitbetaalgegevens', 'Btw-status', 'Bedrag', 'Omschrijving', 'Samenwerking']];
        foreach ($run->payouts()->with('creator.user', 'collaboration.company')->orderBy('id')->get() as $p) {
            $regels[] = [
                $p->creator->user->name, $p->creator->user->email, (string) $p->creator->payout_details, $p->creator->vat_status ?? 'niet opgegeven',
                number_format($p->amount_cents / 100, 2, ',', ''), "MEBRANDING vergoeding {$p->collaboration->number}", $p->collaboration->company->name,
            ];
        }
        $run->update(['exported_at' => now()]);

        $uit = fopen('php://temp', 'r+');
        foreach ($regels as $r) {
            fputcsv($uit, $r, ';', '"', '');
        }
        rewind($uit);

        return "\xEF\xBB\xBF".stream_get_contents($uit);
    }

    /** Na de overboeking via de bank: run en vergoedingen op uitbetaald, creator krijgt c-uitbetaling-verstuurd. */
    public function markeerUitbetaald(PayoutRun $run, User $door): void
    {
        if ($run->status === PayoutRun::UITBETAALD) {
            throw ValidationException::withMessages(['run' => 'Deze betaalrun is al uitbetaald.']);
        }
        $payouts = $run->payouts()->with('creator.user', 'collaboration.company')->get();
        if ($payouts->isEmpty()) {
            throw ValidationException::withMessages(['run' => 'Er zit niets in deze betaalrun.']);
        }

        DB::transaction(function () use ($run, $door, $payouts) {
            Payout::whereIn('id', $payouts->pluck('id'))->update(['status' => 'uitbetaald', 'paid_at' => now()]);
            $run->update(['status' => PayoutRun::UITBETAALD, 'paid_at' => now(), 'paid_by' => $door->id]);

            foreach ($payouts as $p) {
                $this->logSamenwerking($p->collaboration_id, $door, 'vergoeding_uitbetaald', null, ['bedrag' => $p->amount_cents, 'betaalrun' => $run->run_on->toDateString()]);
                $this->melder->stuur('c-uitbetaling-verstuurd', $p->creator->user, [
                    'bedrag' => self::euro($p->amount_cents), 'zaak' => $p->collaboration->company->name,
                    'samenwerking' => $p->collaboration->title, 'nummer' => $p->collaboration->number,
                ], route('creator.profiel.vergoedingen'));
            }
        });
    }

    /* ----------------------------------------------------------------- Maandelijks: tegoed en factuur (1e van de maand) */

    /**
     * Op de 1e van de maand, per actief abonnement: ongebruikt tegoed volgens de instelling, nieuw tegoed toekennen, factuur maken.
     * Veilig om vaker te draaien: per bedrijf en periode één factuur.
     */
    public function maandelijks(?CarbonInterface $dag = null): int
    {
        $start = CarbonImmutable::parse($dag ?? today())->startOfMonth();
        $eind = $start->endOfMonth()->startOfDay();
        $aantal = 0;

        $abonnementen = Subscription::with('company', 'planVersion.plan')->where('status', 'actief')
            ->whereDate('starts_on', '<=', $eind->toDateString())
            ->where(fn ($q) => $q->whereNull('cancels_on')->orWhereDate('cancels_on', '>=', $start->toDateString()))
            ->get();

        foreach ($abonnementen as $abo) {
            if (Invoice::where('company_id', $abo->company_id)->whereDate('period_start', $start->toDateString())->exists()) {
                continue;
            }
            $this->maandVoorAbonnement($abo, $start, $eind);
            $aantal++;
        }

        return $aantal;
    }

    private function maandVoorAbonnement(Subscription $abo, CarbonImmutable $start, CarbonImmutable $eind): Invoice
    {
        // Prijswijzigingen gelden alleen voor nieuwe perioden: de versie die geldt op de eerste dag van de periode.
        $versie = PlanVersion::where('plan_id', $abo->planVersion->plan_id)->whereDate('valid_from', '<=', $start->toDateString())
            ->latest('valid_from')->first() ?? $abo->planVersion;
        $plan = $abo->planVersion->plan->name;
        $maand = $start->translatedFormat('F Y');
        $bedrijf = $abo->company;

        return DB::transaction(function () use ($abo, $versie, $plan, $maand, $bedrijf, $start, $eind) {
            $over = $bedrijf->availablePoints();
            if (config('geld.unused_credit') === 'vervallen' && $over > 0) {
                $bedrijf->ledger()->create(['kind' => CreditLedger::CORRECTIE, 'points' => -$over, 'reason' => "Ongebruikt tegoed vervallen bij de start van {$maand} (VOORSTEL)"]);
            }
            $bedrijf->ledger()->create(['kind' => CreditLedger::TOEKENNEN, 'points' => $versie->points_per_month, 'reason' => "Tegoed {$maand} ({$plan})"]);

            $prijs = $versie->price_cents;
            $btw = (int) round($prijs * config('geld.vat_rate') / 100);
            $factuur = Invoice::create([
                'company_id' => $bedrijf->id, 'subscription_id' => $abo->id, 'number' => $this->nummer($start),
                'period_start' => $start->toDateString(), 'period_end' => $eind->toDateString(),
                'lines' => [[
                    'omschrijving' => "Abonnement {$plan}, {$maand}", 'toelichting' => "{$versie->points_per_month} punten tegoed",
                    'aantal' => 1, 'prijs_cents' => $prijs, 'bedrag_cents' => $prijs,
                ]],
                'subtotal_cents' => $prijs, 'vat_rate' => config('geld.vat_rate'), 'vat_cents' => $btw, 'total_cents' => $prijs + $btw,
                'status' => Invoice::OPENSTAAND, 'issued_on' => today()->toDateString(),
                'due_on' => today()->addDays(config('geld.payment_days'))->toDateString(),
            ]);

            ActivityLog::create([
                'subject_type' => $bedrijf->getMorphClass(), 'subject_id' => $bedrijf->id, 'event' => 'factuur_aangemaakt',
                'data' => ['factuur' => $factuur->number, 'punten' => $versie->points_per_month, 'over' => $over, 'ongebruikt' => config('geld.unused_credit')],
            ]);

            $this->melder->stuur('o-factuur', $bedrijf->owners()->get(), [
                'nummer' => $factuur->number, 'omschrijving' => 'abonnement '.$plan.', '.$start->translatedFormat('F'),
                'bedrag' => self::euro($factuur->total_cents), 'datum' => $factuur->due_on->translatedFormat('l j F'), 'zaak' => $bedrijf->name,
            ], route('ondernemer.facturen.toon', $factuur));

            return $factuur;
        });
    }

    private function nummer(CarbonInterface $start): string
    {
        $prefix = config('geld.invoice_prefix').'-'.$start->year.'-';
        $laatste = Invoice::where('number', 'like', $prefix.'%')->max('number');
        $volgend = $laatste ? ((int) substr($laatste, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $volgend, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Herinneringen na de vervaldatum (o-factuur-vervallen, dagen uit config geld.reminder_days), naar de eigenaren en de accountmanager.
     * Bij de eerste herinnering krijgt Financieel een teamsignaal (t-factuur), één keer per factuur.
     */
    public function herinneringen(): int
    {
        $aantal = 0;
        $dagen = config('geld.reminder_days');

        foreach (Invoice::with('company.accountManager')->where('status', Invoice::OPENSTAAND)->whereDate('due_on', '<', today())->get() as $f) {
            $teLaat = (int) $f->due_on->diffInDays(today());
            $nodig = count(array_filter($dagen, fn ($d) => $teLaat >= $d));
            if ($nodig <= $f->reminders_sent) {
                continue;
            }
            $eerste = (int) $f->reminders_sent === 0;
            $f->update(['reminders_sent' => $nodig, 'last_reminder_at' => now()]);
            $vars = ['nummer' => $f->number, 'bedrag' => self::euro($f->total_cents), 'datum' => $f->due_on->translatedFormat('l j F'), 'zaak' => $f->company->name];
            $this->melder->stuur('o-factuur-vervallen', $f->company->owners()->get()->push($f->company->accountManager), $vars, route('ondernemer.facturen.toon', $f));
            if ($eerste) {
                $financieel = $this->melder->team('financieel.bekijken')->merge($this->melder->team('financieel.beheren'));
                $this->melder->stuur('t-factuur', $financieel, $vars, route('team.financieel.factuur', $f));
            }
            $aantal++;
        }

        return $aantal;
    }

    public function markeerBetaald(Invoice $f, User $door): void
    {
        if ($f->status === Invoice::BETAALD) {
            throw ValidationException::withMessages(['factuur' => 'Deze factuur is al betaald.']);
        }
        $f->update(['status' => Invoice::BETAALD, 'paid_at' => now()]);
        ActivityLog::create([
            'user_id' => $door->id, 'subject_type' => (new Company)->getMorphClass(), 'subject_id' => $f->company_id,
            'event' => 'factuur_betaald', 'data' => ['factuur' => $f->number, 'bedrag' => $f->total_cents],
        ]);
    }

    private function logSamenwerking(int $id, ?User $door, string $event, ?string $reden, array $data): void
    {
        ActivityLog::create([
            'user_id' => $door?->id, 'subject_type' => (new Collaboration)->getMorphClass(), 'subject_id' => $id,
            'event' => $event, 'reason' => $reden, 'data' => $data,
        ]);
    }
}
