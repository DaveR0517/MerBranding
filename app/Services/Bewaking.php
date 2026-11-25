<?php

namespace App\Services;

use App\Http\Presenters\ScorePresenter;
use App\Models\Company;
use App\Models\CourseDay;
use App\Models\Creator;
use App\Models\Enrollment;
use App\Models\PayoutRun;
use App\Models\Score as ScoreRij;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Services\Koppelingen\Koppelingen;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Tijdgestuurde berichten over geld, koppelingen, score en maandoverzicht, en academy (berichtsjablonen, moment uit FO 3.6 en 8.4).
 * Elk bericht gaat maar één keer per ding: de sleutel komt in de kolom herinneringen van dat ding (HeeftHerinneringen).
 * Gepland in routes/console.php; elke methode geeft het aantal verstuurde berichten terug.
 */
class Bewaking
{
    public function __construct(
        private Melder $melder,
        private Geld $geld,
        private Maandrapport $maandrapport,
        private ScorePresenter $score,
        private Koppelingen $koppelingen,
    ) {}

    /* ----------------------------------------------------------------- Geld */

    /** o-tegoed: het tegoed is nog genoeg voor precies één samenwerking (van het goedkoopste niveau). Eén keer per maand. */
    public function tegoed(): int
    {
        $goedkoopste = $this->goedkoopsteSamenwerking();
        $aantal = 0;

        foreach ($this->abonnementen() as $abo) {
            $bedrijf = $abo->company;
            if ($bedrijf->onbeperkt_tegoed || intdiv(max(0, $bedrijf->availablePoints()), $goedkoopste) !== 1
                || ! $bedrijf->markeerHerinnering('tegoed-'.today()->format('Y-m'))) {
                continue;
            }
            $this->melder->stuur('o-tegoed', $bedrijf->owners()->get(), ['n' => 1, 'pakket' => $abo->planVersion->plan->name], route('ondernemer.beheer.abonnement'));
            $aantal++;
        }

        return $aantal;
    }

    /**
     * t-tegoed-niet-benut: een paar dagen voor het einde van de maand (config geld.unused_credit_warn_days) de ondernemers met tegoed over.
     * Per accountmanager zijn eigen bedrijven; bedrijven zonder accountmanager naar wie bedrijven beheert. Elk bedrijf één keer per maand.
     */
    public function tegoedNietBenut(): int
    {
        $dagen = (int) today()->diffInDays(today()->endOfMonth()->startOfDay());
        if ($dagen < 1 || $dagen > (int) config('geld.unused_credit_warn_days')) {
            return 0;
        }

        $bedrijven = $this->abonnementen()->pluck('company')->filter(fn (Company $b) => ! $b->onbeperkt_tegoed && $b->availablePoints() > 0
            && $b->markeerHerinnering('tegoed-niet-benut-'.today()->format('Y-m')));

        $aantal = 0;
        foreach ($bedrijven->groupBy(fn (Company $b) => (int) $b->account_manager_id) as $am => $groep) {
            $aan = $am ? $groep->first()->accountManager : $this->melder->team('bedrijven.beheren');
            $this->melder->stuur('t-tegoed-niet-benut', $aan, ['aantal' => $groep->count(), 'dagen' => $dagen], route('team.bedrijven'));
            $aantal++;
        }

        return $aantal;
    }

    /** t-vrijgave: een samengestelde betaalrun met vergoedingen staat klaar om uit te betalen. Eén keer per run. */
    public function vrijgave(): int
    {
        $aantal = 0;

        foreach (PayoutRun::where('status', PayoutRun::SAMENGESTELD)->get() as $run) {
            $vergoedingen = $run->payouts()->count();
            if ($vergoedingen === 0 || ! $run->markeerHerinnering('vrijgave')) {
                continue;
            }
            $this->melder->stuur('t-vrijgave', $this->melder->team('financieel.beheren'), [
                'aantal' => $vergoedingen, 'bedrag' => Geld::euro((int) $run->payouts()->sum('amount_cents')), 'datum' => $run->run_on->translatedFormat('j F'),
            ], route('team.financieel'));
            $aantal++;
        }

        return $aantal;
    }

    /** c-gegevens-ontbreken voor de betaalrun: opeisbare vergoedingen blijven staan omdat de uitbetaalgegevens ontbreken. Eén keer per creator per run. */
    public function gegevensOntbreken(): int
    {
        $run = Geld::vrijdag()->toDateString();
        $aantal = 0;

        foreach ($this->geld->ingehouden()->groupBy('creator_id') as $vergoedingen) {
            $creator = $vergoedingen->first()->creator;
            if (! $creator->markeerHerinnering("gegevens-{$run}")) {
                continue;
            }
            $this->melder->stuur('c-gegevens-ontbreken', $creator->user, ['bedrag' => Geld::euro((int) $vergoedingen->sum('amount_cents'))], route('creator.profiel.account'));
            $aantal++;
        }

        return $aantal;
    }

    /* ----------------------------------------------------------------- Koppelingen */

    /**
     * Koppelingen van creators en bedrijven: c-/o-koppeling-verloopt binnen de waarschuwingstermijn (config content.koppelingen.warn_days),
     * c-/o-koppeling-weg als de koppeling verlopen is of een fout gaf. Het team krijgt het aantal in t-token.
     * Einddatum is expires_at: dan moet de eigenaar opnieuw inloggen (bij TikTok is token_expires_at het toegangstoken van een dag).
     * Eén keer per koppeling per einddatum: na opnieuw koppelen is er een nieuwe einddatum.
     */
    public function koppelingen(): int
    {
        $grens = now()->addDays((int) config('content.koppelingen.warn_days'));
        $gemeld = 0;
        $aantal = 0;

        foreach (SocialAccount::with('owner')->whereIn('status', ['gekoppeld', 'verlopen', 'fout'])->get() as $account) {
            $weg = $account->status !== 'gekoppeld' || $account->expires_at?->isPast();
            if (! $weg && ($account->expires_at === null || $account->expires_at->gt($grens))) {
                continue;
            }
            $eigenaar = $account->owner;
            [$rol, $aan, $url] = match (true) {
                $eigenaar instanceof Creator => ['c', collect([$eigenaar->user]), route('creator.content.koppelingen')],
                $eigenaar instanceof Company => ['o', $eigenaar->owners()->get()->when($weg, fn ($c) => $c->push($eigenaar->accountManager)), route('ondernemer.content.koppelingen')],
                default => [null, null, null],
            };
            $soort = $weg ? 'weg' : 'verloopt';
            if (! $rol || ! $account->markeerHerinnering($soort.'-'.($account->expires_at?->toDateString() ?? 'zonder-einde'))) {
                continue;
            }

            $vars = ['platform' => $this->koppelingen->platform($account->platform)->naam()];
            if (! $weg) {
                $vars['datum'] = $account->expires_at->translatedFormat('l j F');
            }
            $this->melder->stuur("{$rol}-koppeling-{$soort}", $aan, $vars, $url);
            $gemeld++;
            $aantal++;
        }

        if ($gemeld > 0) {
            $this->melder->stuur('t-token', $this->melder->team('koppelingen.beheren'), ['aantal' => $gemeld], route('team.content.koppelingen'));
            $aantal++;
        }

        return $aantal;
    }

    /* ----------------------------------------------------------------- Maandoverzicht en score (eerste werkdag van de maand) */

    /** o-maandoverzicht: op de eerste werkdag, voor elk bedrijf waar vorige maand iets liep. Eén keer per bedrijf per maand. */
    public function maandoverzicht(): int
    {
        if (! $this->eersteWerkdag()) {
            return 0;
        }
        $maand = CarbonImmutable::today()->subMonthNoOverflow()->startOfMonth();
        $aantal = 0;

        foreach (Company::all() as $bedrijf) {
            $rapport = $this->maandrapport->voor($bedrijf, $maand);
            if (($rapport['samenwerkingen']->isEmpty() && $rapport['publicaties'] === []) || ! $bedrijf->markeerHerinnering('maandoverzicht-'.$maand->format('Y-m'))) {
                continue;
            }
            $this->melder->stuur('o-maandoverzicht', $bedrijf->users()->get(), [
                'maand' => $maand->translatedFormat('F'), 'zaak' => $bedrijf->name,
                'aantal' => count($rapport['publicaties']), 'views' => $this->views($rapport['platforms']),
            ], route('ondernemer.maandrapport', $maand->format('Y-m')));
            $aantal++;
        }

        return $aantal;
    }

    /** c-maandscore: op de eerste werkdag (na de niveaumeting van de 1e) de score en de plek in het klassement. Eén keer per creator per maand. */
    public function maandscore(): int
    {
        if (! $this->eersteWerkdag()) {
            return 0;
        }
        $maand = CarbonImmutable::today()->subMonthNoOverflow();
        $ranglijst = $this->score->laatsteScores()->values();
        $aantal = 0;

        foreach (Creator::where('status', 'actief')->with('user')->get() as $creator) {
            $rij = $ranglijst->firstWhere('creator_id', $creator->id);
            if (! $rij || ! $creator->markeerHerinnering('maandscore-'.$maand->format('Y-m'))) {
                continue;
            }
            // Zelfde klassement als het scherm CreatorScore: nieuw talent staat in een eigen lijst.
            $lijst = $rij->is_new_talent ? $ranglijst->filter(fn (ScoreRij $r) => $r->is_new_talent)->values() : $ranglijst;
            $this->melder->stuur('c-maandscore', $creator->user, [
                'maand' => $maand->translatedFormat('F'), 'score' => number_format($rij->total, 1, ',', '.'),
                'plek' => $lijst->search(fn (ScoreRij $r) => $r->creator_id === $creator->id) + 1,
                'regio' => $rij->is_new_talent ? 'Nieuw talent' : 'alle creators',
            ], route('creator.score'));
            $aantal++;
        }

        return $aantal;
    }

    /* ----------------------------------------------------------------- Academy */

    /** a-herinnering: 7 dagen en 1 dag voor de cursusdag, aan wie een plek heeft. Eén keer per inschrijving per moment. */
    public function cursusdagHerinnering(): int
    {
        $aantal = 0;

        foreach ([7, 1] as $n) {
            foreach (CourseDay::where('status', CourseDay::OPEN)->whereDate('date', today()->addDays($n))->get() as $dag) {
                foreach ($dag->enrollments()->where('status', Enrollment::INGESCHREVEN)->with('user')->get() as $inschrijving) {
                    if (! $inschrijving->markeerHerinnering("herinnering-{$n}")) {
                        continue;
                    }
                    $this->melder->stuur('a-herinnering', $inschrijving->user, [
                        'n' => $n, 'cursusdag' => $dag->naam(), 'plaats' => $dag->city, 'tijd' => substr($dag->starts_at, 0, 5), 'adres' => $dag->adres(),
                    ], route('academy.cursusdag', $dag));
                    $aantal++;
                }
            }
        }

        return $aantal;
    }

    /** t-academy: een open cursusdag binnen een week heeft minder inschrijvingen dan het minimum (config academy.min_participants). Eén keer per dag. */
    public function academyMinimum(): int
    {
        $minimum = (int) config('academy.min_participants');
        $aantal = 0;

        foreach (CourseDay::where('status', CourseDay::OPEN)->whereDate('date', '>=', today())->whereDate('date', '<=', today()->addDays(7))->get() as $dag) {
            $bezet = $dag->bezet();
            if ($bezet >= $minimum || ! $dag->markeerHerinnering('onder-minimum')) {
                continue;
            }
            $this->melder->stuur('t-academy', $this->melder->team('academy.beheren'), [
                'cursusdag' => $dag->naam(), 'datum' => $dag->datumTekst(), 'aantal' => $bezet,
            ], route('team.academy.cursusdag', $dag));
            $aantal++;
        }

        return $aantal;
    }

    /* ----------------------------------------------------------------- Intern */

    /** Lopende abonnementen vandaag, één per bedrijf. */
    private function abonnementen(): Collection
    {
        $vandaag = today()->toDateString();

        return Subscription::with('company', 'planVersion.plan')->where('status', 'actief')->whereDate('starts_on', '<=', $vandaag)
            ->where(fn ($q) => $q->whereNull('cancels_on')->orWhereDate('cancels_on', '>=', $vandaag))
            ->latest('starts_on')->get()->unique('company_id');
    }

    /** Punten van de goedkoopste samenwerking (laagste creatorniveau). */
    private function goedkoopsteSamenwerking(): int
    {
        return max(1, (int) collect(config('mebranding.levels'))->pluck('points')->filter()->min());
    }

    private function eersteWerkdag(): bool
    {
        $dag = today()->startOfMonth();
        while ($dag->isWeekend()) {
            $dag->addDay();
        }

        return today()->isSameDay($dag);
    }

    /** Views per platform; over platforms heen wordt niet opgeteld (Cijfers::perPlatform). */
    private function views(array $platforms): string
    {
        $gemeten = collect($platforms)->filter(fn (array $p) => ($p['totals']['views'] ?? null) !== null);
        if ($gemeten->isEmpty()) {
            return 'nog geen gemeten';
        }
        $getal = fn (array $p) => number_format($p['totals']['views'], 0, ',', '.');
        if ($gemeten->count() === 1) {
            return $getal($gemeten->first());
        }

        return $gemeten->map(fn (array $p) => $getal($p).' ('.$this->platformNaam($p['platform']).')')->join(', ', ' en ');
    }

    private function platformNaam(string $sleutel): string
    {
        return in_array($sleutel, $this->koppelingen->sleutels(), true) ? $this->koppelingen->platform($sleutel)->naam() : ucfirst($sleutel);
    }
}
