<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Certificate;
use App\Models\CourseDay;
use App\Models\Creator;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Regels van de academy (docs/02 flow 3): inschrijven en wachtlijst, doorschuiven, check-in op de dag,
 * dag afsluiten, praktijkexamen beoordelen, certificaat uitreiken en doorstroom naar creator.
 */
class Academy
{
    public function __construct(private Melder $melder) {}

    /**
     * Inschrijven. Is de dag vol, dan op de wachtlijst (zonder te betalen).
     *
     * @param  array<string, mixed>  $gegevens  telefoon, woonplaats, kanalen, geboortedatum
     */
    public function inschrijven(CourseDay $dag, User $user, array $gegevens = []): Enrollment
    {
        if ($dag->status !== CourseDay::OPEN || $dag->date->lt(today())) {
            throw ValidationException::withMessages(['dag' => 'Voor deze cursusdag kun je je niet meer inschrijven.']);
        }

        $inschrijving = DB::transaction(function () use ($dag, $user, $gegevens) {
            CourseDay::whereKey($dag->id)->lockForUpdate()->first();
            $bestaand = Enrollment::where('course_day_id', $dag->id)->where('user_id', $user->id)->first();
            if ($bestaand && $bestaand->status !== Enrollment::AFGEMELD) {
                throw ValidationException::withMessages(['dag' => 'Je staat al ingeschreven voor deze cursusdag.']);
            }

            $velden = collect($gegevens)->only(['phone', 'city', 'tiktok', 'instagram', 'birthdate'])->all();
            $status = $dag->vrij() > 0 ? Enrollment::INGESCHREVEN : Enrollment::WACHTLIJST;
            $data = $velden + ['status' => $status, 'paid_status' => Enrollment::NOG_TE_BETALEN, 'paid_at' => null, 'offer_expires_at' => null];

            if ($bestaand) { // opnieuw na afmelden: achteraan op de wachtlijst
                $bestaand->forceFill($data + ['created_at' => now()])->save();

                return $bestaand;
            }

            return Enrollment::create($data + ['course_day_id' => $dag->id, 'user_id' => $user->id, 'checkin_code' => Str::random(32)]);
        });

        $this->log($user, $inschrijving, $inschrijving->status === Enrollment::WACHTLIJST ? 'academy_wachtlijst' : 'academy_ingeschreven');
        $vars = ['cursusdag' => $dag->naam(), 'datum' => $dag->datumTekst(), 'plaats' => $dag->city];

        if ($inschrijving->status === Enrollment::WACHTLIJST) {
            $this->melder->stuur('a-wachtlijst', $user, $vars + ['plek' => $inschrijving->plek()], route('academy.cursusdag', $dag));
        } else {
            // VOORSTEL: zolang er geen betaalpartij is, gaat de bevestiging direct bij inschrijven; betalen zet het team op betaald.
            $this->melder->stuur('a-ingeschreven', $user, $vars, route('academy.overzicht'));
        }

        return $inschrijving;
    }

    /** Afmelden (student of team). Een vrijgekomen plek schuift door naar de wachtlijst. */
    public function afmelden(Enrollment $inschrijving, ?User $door = null): void
    {
        if (! in_array($inschrijving->status, [Enrollment::INGESCHREVEN, Enrollment::WACHTLIJST], true) || $inschrijving->courseDay->status !== CourseDay::OPEN) {
            throw ValidationException::withMessages(['afmelden' => 'Deze inschrijving kan niet meer worden afgemeld.']);
        }
        $hadPlek = $inschrijving->status === Enrollment::INGESCHREVEN;
        $inschrijving->update(['status' => Enrollment::AFGEMELD, 'offer_expires_at' => null]);
        $this->log($door ?? $inschrijving->user, $inschrijving, 'academy_afgemeld');

        if ($hadPlek) {
            $this->doorschuiven($inschrijving->courseDay);
        }
    }

    /**
     * Cursusdag bijwerken (team). Nooit minder plekken dan er bezet zijn; meer plekken laten de wachtlijst doorschuiven.
     * Veranderen datum, tijd of plaats, dan krijgen deelnemers en wachtenden a-gewijzigd met wat er anders is.
     *
     * @param  array<string, mixed>  $data
     * @return list<Enrollment> wie van de wachtlijst doorschoof
     */
    public function cursusdagBijwerken(CourseDay $dag, array $data): array
    {
        $data['max_participants'] = max($data['max_participants'], $dag->bezet());
        $voor = $this->moment($dag);
        $dag->update($data);
        $wijziging = $this->wijziging($voor, $this->moment($dag->fresh()));

        if ($wijziging !== null && $dag->status === CourseDay::OPEN && $dag->date->gte(today())) {
            $wie = $dag->enrollments()->whereIn('status', [Enrollment::INGESCHREVEN, Enrollment::WACHTLIJST])->with('user')->get()->pluck('user');
            $this->melder->stuur('a-gewijzigd', $wie, ['cursusdag' => $dag->naam(), 'wijziging' => $wijziging], route('academy.cursusdag', $dag));
        }

        return $this->doorschuiven($dag);
    }

    /**
     * Vrije plekken gaan naar de eerste op de wachtlijst. Die krijgt de melding "plek vrij" met een betaaltermijn (VOORSTEL 24 uur).
     *
     * @return list<Enrollment> wie doorschoof
     */
    public function doorschuiven(CourseDay $dag): array
    {
        if ($dag->status !== CourseDay::OPEN) {
            return [];
        }

        $geschoven = [];
        foreach ($dag->wachtlijst()->take($dag->vrij()) as $volgende) {
            $termijn = now()->addHours((int) config('academy.offer_hours'));
            $volgende->update(['status' => Enrollment::INGESCHREVEN, 'paid_status' => Enrollment::NOG_TE_BETALEN, 'offer_expires_at' => $termijn]);
            $this->log(null, $volgende, 'academy_plek_vrij');
            $this->melder->stuur('a-plek-vrij', $volgende->user, [
                'cursusdag' => $dag->naam(), 'datum' => $dag->datumTekst(), 'tijd' => $termijn->translatedFormat('D j M H:i'),
            ], route('academy.cursusdag', $dag));
            $geschoven[] = $volgende;
        }

        return $geschoven;
    }

    /** Betaalstatus handmatig (betaalpartij nog niet gekozen). */
    public function betaald(Enrollment $inschrijving, bool $betaald, User $door): void
    {
        $inschrijving->update([
            'paid_status' => $betaald ? Enrollment::BETAALD : Enrollment::NOG_TE_BETALEN,
            'paid_at' => $betaald ? now() : null,
            'offer_expires_at' => $betaald ? null : $inschrijving->offer_expires_at,
        ]);
        $this->log($door, $inschrijving, $betaald ? 'academy_betaald' : 'academy_niet_betaald');
    }

    /** Check-in: alleen op de dag zelf en alleen met een plek. Dubbel inchecken wordt geweigerd met het eerste tijdstip. */
    public function inchecken(Enrollment $inschrijving, User $door, ?string $reden = null): void
    {
        $dag = $inschrijving->courseDay;
        if (! $dag->isVandaag()) {
            throw ValidationException::withMessages(['checkin' => 'Inchecken kan alleen op de dag zelf ('.$dag->date->translatedFormat('l j F').').']);
        }
        if ($inschrijving->checked_in_at) {
            throw ValidationException::withMessages(['checkin' => 'Al ingecheckt om '.$inschrijving->checked_in_at->format('H:i').'.']);
        }
        if (! in_array($inschrijving->status, [Enrollment::INGESCHREVEN, Enrollment::AFWEZIG], true)) {
            throw ValidationException::withMessages(['checkin' => 'Deze deelnemer heeft geen plek op deze dag.']);
        }

        $inschrijving->update(['status' => Enrollment::AANWEZIG, 'checked_in_at' => now(), 'checked_in_by' => $door->id, 'checkin_reason' => $reden]);
        $this->log($door, $inschrijving, 'academy_ingecheckt', $reden);
    }

    /**
     * Dag afsluiten: wie niet incheckte krijgt Afwezig. Met praktijkexamen als voorwaarde staat het examen open,
     * anders gaat het certificaat direct uit.
     */
    public function dagAfsluiten(CourseDay $dag, User $door): void
    {
        if ($dag->status !== CourseDay::OPEN) {
            throw ValidationException::withMessages(['dag' => 'Deze cursusdag is al afgesloten.']);
        }

        DB::transaction(function () use ($dag, $door) {
            $dag->enrollments()->where('status', Enrollment::INGESCHREVEN)->update(['status' => Enrollment::AFWEZIG]);
            $dag->enrollments()->where('status', Enrollment::WACHTLIJST)->update(['status' => Enrollment::AFGEMELD]);
            $dag->update(['status' => CourseDay::AFGESLOTEN, 'closed_at' => now()]);

            foreach ($dag->enrollments()->where('status', Enrollment::AANWEZIG)->get() as $inschrijving) {
                if ($dag->exam_required) {
                    Exam::firstOrCreate(['enrollment_id' => $inschrijving->id], ['status' => Exam::OPEN, 'due_at' => now()->addDays((int) config('academy.exam_days'))->endOfDay()]);
                } else {
                    $this->certificaat($inschrijving, $door);
                }
            }
        });

        ActivityLog::create(['user_id' => $door->id, 'subject_type' => $dag->getMorphClass(), 'subject_id' => $dag->id, 'event' => 'academy_dag_afgesloten']);
    }

    /** Hoort deze uitslag bij Voldoende? Gemiddeld minimaal 3 en geen enkele 1. */
    public static function voldoende(array $scores): bool
    {
        $scores = array_map('intval', $scores);

        return $scores !== [] && min($scores) > 1 && array_sum($scores) / count($scores) >= (float) config('academy.pass_average');
    }

    /**
     * Praktijkexamen beoordelen. Voldoende: certificaat. Anders: één herkansing (VOORSTEL 14 dagen), daarna onvoldoende.
     *
     * @param  array<string, int>  $scores  per criterium (label) 1 tot 5
     */
    public function examenBeoordelen(Exam $examen, array $scores, ?string $feedback, User $door): void
    {
        if ($examen->status !== Exam::INGELEVERD) {
            throw ValidationException::withMessages(['examen' => 'Dit examen is niet ingeleverd of al beoordeeld.']);
        }
        $voldoende = self::voldoende($scores);
        $status = $voldoende ? Exam::VOLDOENDE : ($examen->attempt < 2 ? Exam::VERBETEREN : Exam::ONVOLDOENDE);

        DB::transaction(function () use ($examen, $scores, $feedback, $door, $status, $voldoende) {
            $examen->update([
                'status' => $status, 'scores' => $scores, 'average' => round(array_sum($scores) / max(1, count($scores)), 1), 'feedback' => $feedback,
                'assessor_id' => $door->id, 'assessed_at' => now(),
                'due_at' => $status === Exam::VERBETEREN ? now()->addDays((int) config('academy.retake_days'))->endOfDay() : $examen->due_at,
            ]);
            if ($voldoende) {
                $this->certificaat($examen->enrollment, $door);
            }
        });

        $this->log($door, $examen->enrollment, 'academy_examen_'.$status);
    }

    /** Certificaat uitreiken: uniek nummer, geheime controlecode, status Geslaagd, meldingen certificaat en Word creator. */
    public function certificaat(Enrollment $inschrijving, User $door): Certificate
    {
        if ($inschrijving->certificate) {
            return $inschrijving->certificate;
        }
        if (! in_array($inschrijving->status, [Enrollment::AANWEZIG, Enrollment::GESLAAGD], true)) {
            throw ValidationException::withMessages(['certificaat' => 'Alleen wie aanwezig was, kan een certificaat krijgen.']);
        }

        $certificaat = DB::transaction(function () use ($inschrijving, $door) {
            $inschrijving->update(['status' => Enrollment::GESLAAGD]);
            $certificaat = Certificate::create([
                'enrollment_id' => $inschrijving->id, 'user_id' => $inschrijving->user_id, 'issued_at' => now(), 'issued_by' => $door->id,
                'number' => 'tijdelijk-'.Str::uuid(), 'verification_code' => Str::lower(Str::random(32)),
            ]);
            // Volgnummer uit de database-id: altijd uniek, ook als twee teamleden tegelijk uitreiken.
            $certificaat->update(['number' => sprintf('MB-ACA-%s-%06d', now()->year, $certificaat->id)]);

            return $certificaat;
        });

        $this->log($door, $inschrijving, 'academy_certificaat', $certificaat->number);
        $this->melder->stuur('a-certificaat', $inschrijving->user, ['nummer' => $certificaat->number], route('academy.overzicht'));
        if (! Creator::where('user_id', $inschrijving->user_id)->exists()) {
            $this->melder->stuur('a-word-creator', $inschrijving->user, [], route('academy.overzicht'));
        }

        return $certificaat;
    }

    /**
     * Doorstroom: met certificaat een creatorprofiel in screening (Bronze), gegevens uit de inschrijving.
     * De rol Creator komt erbij; de rol Student blijft. Het team krijgt een screeningstaak in de werkvoorraad.
     */
    public function wordCreator(User $user): Creator
    {
        $certificaat = Certificate::where('user_id', $user->id)->latest('issued_at')->first();
        if (! $certificaat) {
            throw ValidationException::withMessages(['creator' => 'Je kunt creator worden zodra je certificaat er is.']);
        }
        if ($bestaand = Creator::where('user_id', $user->id)->first()) {
            return $bestaand;
        }

        $gegevens = $certificaat->enrollment;
        $creator = DB::transaction(function () use ($user, $gegevens) {
            $creator = Creator::create([
                'user_id' => $user->id, 'level' => 'bronze', 'kind' => 'beide', 'status' => 'screening',
                'city' => $gegevens->city, 'birthdate' => $gegevens->birthdate,
                'instagram' => $gegevens->instagram ? ltrim($gegevens->instagram, '@') : null,
                'tiktok' => $gegevens->tiktok ? ltrim($gegevens->tiktok, '@') : null,
            ]);
            $user->assignRole('Creator');
            Task::create([
                'type' => 'screening', 'title' => "Screenen: {$user->name} (academy-gecertificeerd)",
                'creator_id' => $creator->id, 'permission' => 'creators.screenen',
                'due_at' => now()->addWeekdays((int) config('academy.screening_workdays')),
            ]);

            return $creator;
        });

        ActivityLog::create(['user_id' => $user->id, 'subject_type' => $creator->getMorphClass(), 'subject_id' => $creator->id, 'event' => 'academy_word_creator', 'reason' => $certificaat->number]);

        return $creator;
    }

    /** Datum, tijden en plaats zoals deelnemers ze lezen. */
    private function moment(CourseDay $dag): array
    {
        return [
            'datum' => $dag->date->translatedFormat('l j F'),
            'tijd' => $dag->tijden(),
            'plaats' => $dag->adres(),
        ];
    }

    /** "nieuwe datum zaterdag 12 december, nieuwe tijd 10:00 - 16:00", of null als niets veranderde. */
    private function wijziging(array $voor, array $na): ?string
    {
        $delen = [];
        foreach (['datum' => 'nieuwe datum', 'tijd' => 'nieuwe tijd', 'plaats' => 'nieuwe plaats'] as $sleutel => $label) {
            if ($voor[$sleutel] !== $na[$sleutel]) {
                $delen[] = "{$label} {$na[$sleutel]}";
            }
        }

        return $delen ? implode(', ', $delen) : null;
    }

    private function log(?User $door, Enrollment $inschrijving, string $event, ?string $reden = null): void
    {
        ActivityLog::create([
            'user_id' => $door?->id, 'subject_type' => $inschrijving->getMorphClass(), 'subject_id' => $inschrijving->id,
            'event' => $event, 'reason' => $reden, 'data' => ['course_day_id' => $inschrijving->course_day_id, 'status' => $inschrijving->status],
        ]);
    }
}
