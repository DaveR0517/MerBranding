<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Presenters\AcademyPresenter as P;
use App\Models\Assignment;
use App\Models\CourseDay;
use App\Models\Creator;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Submission;
use App\Models\User;
use App\Services\Academy;
use App\Services\Melder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Academybeheer (AdminCursusdagen): cursusdagen, deelnemers en wachtlijst, check-in, beoordelen, certificaten, studenten en lesstof. */
class AcademyController extends Controller implements HasMiddleware
{
    public function __construct(private Academy $academy, private Melder $melder) {}

    public static function middleware(): array
    {
        return [new Middleware('can:academy.beheren')];
    }

    public function cursusdagen(): Response
    {
        return Inertia::render('team/academy/cursusdagen', [
            'days' => CourseDay::orderByDesc('date')->limit(100)->get()->map(fn (CourseDay $d) => P::dag($d) + [
                'certified' => $d->enrollments()->where('status', Enrollment::GESLAAGD)->count(),
                'paid' => $d->enrollments()->whereIn('status', Enrollment::MET_PLEK)->where('paid_status', Enrollment::BETAALD)->count(),
            ])->values(),
            'open_exams' => Exam::where('status', Exam::INGELEVERD)->count(),
            'open_submissions' => Submission::whereNotNull('assignment_id')->whereNull('assessed_at')->count(),
            'defaults' => [
                'price_cents' => (int) config('academy.price_cents'), 'max_participants' => (int) config('academy.max_participants'),
                'exam_required' => (bool) config('academy.exam_required'),
            ],
        ]);
    }

    public function cursusdagOpslaan(Request $request): RedirectResponse
    {
        $dag = CourseDay::create($this->dagGegevens($request) + ['status' => CourseDay::OPEN]);

        return to_route('team.academy.cursusdag', $dag)->with('melding', 'Cursusdag gepland.');
    }

    public function cursusdagBijwerken(Request $request, CourseDay $dag): RedirectResponse
    {
        $data = $this->dagGegevens($request, $dag);
        $data['max_participants'] = max($data['max_participants'], $dag->bezet());
        $dag->update($data);
        $geschoven = $this->academy->doorschuiven($dag); // meer plekken: de wachtlijst schuift door

        return back()->with('melding', 'Cursusdag bijgewerkt.'.($geschoven ? ' '.count($geschoven).' van de wachtlijst kregen een plek.' : ''));
    }

    public function cursusdag(CourseDay $dag): Response
    {
        $inschrijvingen = $dag->enrollments()->with(['user', 'exam.submissions', 'exam.assessor', 'certificate'])->orderBy('created_at')->orderBy('id')->get();
        $creators = Creator::whereIn('user_id', $inschrijvingen->pluck('user_id'))->get()->keyBy('user_id');
        $rij = fn (Enrollment $e) => [
            'id' => $e->id, 'user_id' => $e->user_id, 'name' => $e->user->name, 'email' => $e->user->email,
            'handle' => $e->tiktok ?: $e->instagram, 'city' => $e->city, 'phone' => $e->phone,
            'status' => $e->status, 'status_name' => P::statusNaam($e->status),
            'paid' => $e->paid_status === Enrollment::BETAALD, 'paid_at' => $e->paid_at?->toIso8601String(),
            'offer_expires_at' => $e->offer_expires_at?->toIso8601String(), 'enrolled_at' => $e->created_at->toIso8601String(),
            'checked_in_at' => $e->checked_in_at?->toIso8601String(), 'checkin_reason' => $e->checkin_reason,
            'exam' => P::examen($e->exam), 'certificate' => P::certificaat($e->certificate),
            'creator_status' => $creators->get($e->user_id)?->status,
        ];
        $ingeschreven = $inschrijvingen->pluck('user_id');

        return Inertia::render('team/academy/cursusdag', [
            'day' => P::dag($dag),
            'participants' => $inschrijvingen->whereIn('status', Enrollment::MET_PLEK)->map($rij)->values(),
            'waitlist' => $dag->wachtlijst()->map($rij)->values(),
            'cancelled' => $inschrijvingen->where('status', Enrollment::AFGEMELD)->map($rij)->values(),
            'criteria' => P::examenCriteria(),
            'pass_average' => (float) config('academy.pass_average'),
            'students' => User::role('Student')->whereNotIn('id', $ingeschreven)->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function afsluiten(Request $request, CourseDay $dag): RedirectResponse
    {
        $this->academy->dagAfsluiten($dag, $request->user());

        return back()->with('melding', $dag->exam_required ? 'Dag afgesloten. Het praktijkexamen staat open voor wie aanwezig was.' : 'Dag afgesloten. Wie aanwezig was, kreeg het certificaat.');
    }

    /** Team schrijft een bestaande student in (zelfde regels: vol is wachtlijst). */
    public function inschrijven(Request $request, CourseDay $dag): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', Rule::exists('users', 'id')]]);
        $e = $this->academy->inschrijven($dag, User::findOrFail($data['user_id']));

        return back()->with('melding', $e->status === Enrollment::WACHTLIJST ? "Op de wachtlijst gezet (plek {$e->plek()})." : 'Ingeschreven.');
    }

    /** Handmatig inchecken, met reden (bijvoorbeeld: telefoon leeg). */
    public function inchecken(Request $request, Enrollment $inschrijving): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:190']]);
        $this->academy->inchecken($inschrijving, $request->user(), $data['reason'] ?? 'handmatig');

        return back()->with('melding', "{$inschrijving->user->name} is ingecheckt.");
    }

    /** De QR-code op het ticket opent deze link op de telefoon van het team. */
    public function checkin(Request $request, string $code): RedirectResponse
    {
        $inschrijving = Enrollment::where('checkin_code', $code)->firstOrFail();
        $this->academy->inchecken($inschrijving, $request->user(), 'QR-code');

        return to_route('team.academy.cursusdag', $inschrijving->course_day_id)->with('melding', "{$inschrijving->user->name} is ingecheckt.");
    }

    public function betaald(Request $request, Enrollment $inschrijving): RedirectResponse
    {
        $data = $request->validate(['paid' => ['required', 'boolean']]);
        $this->academy->betaald($inschrijving, (bool) $data['paid'], $request->user());

        return back()->with('melding', $data['paid'] ? 'Op betaald gezet.' : 'Weer op nog te betalen gezet.');
    }

    public function afmelden(Request $request, Enrollment $inschrijving): RedirectResponse
    {
        $this->academy->afmelden($inschrijving, $request->user());

        return back()->with('melding', "{$inschrijving->user->name} is afgemeld.");
    }

    public function examenBeoordelen(Request $request, Exam $examen): RedirectResponse
    {
        $labels = array_column(P::examenCriteria(), 'label');
        $data = $request->validate(['scores' => ['required', 'array'], 'scores.*' => ['required', 'integer', 'between:1,5'], 'feedback' => ['nullable', 'string', 'max:5000']]);
        $scores = $this->scores($data['scores'], $labels);
        $this->academy->examenBeoordelen($examen, $scores, $data['feedback'] ?? null, $request->user());
        $examen->refresh();

        return back()->with('melding', match ($examen->status) {
            Exam::VOLDOENDE => 'Voldoende. Het certificaat is uitgereikt.',
            Exam::VERBETEREN => 'Verbeteren: de student krijgt één herkansing.',
            default => 'Onvoldoende na de herkansing: geen certificaat.',
        });
    }

    public function inzendingen(): Response
    {
        $kaart = fn (Submission $s) => P::inzending($s, 'team.academy') + [
            'student' => $s->user->name, 'assignment' => $s->assignment->title, 'criteria' => $s->assignment->criteria,
        ];

        return Inertia::render('team/academy/inzendingen', [
            'open' => Submission::with(['user', 'assignment'])->whereNotNull('assignment_id')->whereNull('assessed_at')->oldest()->get()->map($kaart)->values(),
            'assessed' => Submission::with(['user', 'assignment', 'assessor'])->whereNotNull('assignment_id')->whereNotNull('assessed_at')->latest('assessed_at')->limit(20)->get()->map($kaart)->values(),
            'exams' => Exam::with(['enrollment.user', 'enrollment.courseDay'])->where('status', Exam::INGELEVERD)->oldest('submitted_at')->get()->map(fn (Exam $e) => [
                'id' => $e->id, 'student' => $e->enrollment->user->name, 'day' => $e->enrollment->courseDay->naam(), 'course_day_id' => $e->enrollment->course_day_id,
                'submitted_at' => $e->submitted_at?->toIso8601String(), 'attempt' => $e->attempt,
            ])->values(),
        ]);
    }

    public function inzendingBeoordelen(Request $request, Submission $inzending): RedirectResponse
    {
        abort_unless($inzending->assignment, 404);
        $data = $request->validate(['scores' => ['required', 'array'], 'scores.*' => ['required', 'integer', 'between:1,5'], 'feedback' => ['nullable', 'string', 'max:5000']]);
        $inzending->update([
            'scores' => $this->scores($data['scores'], array_column($inzending->assignment->criteria, 'label')),
            'feedback' => $data['feedback'] ?? null, 'assessor_id' => $request->user()->id, 'assessed_at' => now(),
        ]);

        return back()->with('melding', 'Beoordeling opgeslagen. De student ziet hem in de opdracht.');
    }

    public function video(Submission $inzending): HttpResponse
    {
        return P::video($inzending);
    }

    public function studenten(): Response
    {
        $studenten = User::role('Student')->orderBy('name')->get();
        $creators = Creator::whereIn('user_id', $studenten->pluck('id'))->get()->keyBy('user_id');
        $inschrijvingen = Enrollment::with(['courseDay', 'certificate'])->whereIn('user_id', $studenten->pluck('id'))->where('status', '!=', Enrollment::AFGEMELD)->get()->groupBy('user_id');

        return Inertia::render('team/academy/studenten', [
            'students' => $studenten->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
                'enrollments' => ($inschrijvingen[$u->id] ?? collect())->map(fn (Enrollment $e) => [
                    'course_day_id' => $e->course_day_id, 'day' => $e->courseDay->naam(), 'date' => $e->courseDay->date->toDateString(),
                    'status_name' => P::statusNaam($e->status), 'certificate' => $e->certificate?->number,
                ])->values(),
                'creator_status' => $creators->get($u->id)?->status,
            ])->values(),
            'days' => CourseDay::where('status', CourseDay::OPEN)->whereDate('date', '>=', today())->orderBy('date')->get()
                ->map(fn (CourseDay $d) => ['id' => $d->id, 'name' => $d->naam().', '.$d->date->translatedFormat('j F')])->values(),
        ]);
    }

    /** Student toevoegen: account met rol Student en een uitnodiging per mail (wachtwoord kiezen), eventueel direct inschrijven. */
    public function studentOpslaan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'course_day_id' => ['nullable', Rule::exists('course_days', 'id')],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::firstOrCreate(['email' => Str::lower($data['email'])], ['name' => $data['name'], 'password' => Str::password(40)]);
            $user->assignRole('Student');

            return $user;
        });
        // Zelfde uitnodiging als bij bedrijven en creators (BeheerController): link om zelf een wachtwoord te kiezen.
        $token = Password::broker()->createToken($user);
        $this->melder->stuur('g-uitnodiging', $user, ['naam' => $request->user()->name, 'zaak' => 'MEBRANDING Academy', 'rol' => 'deelnemer'], route('password.reset', ['token' => $token, 'email' => $user->email]));

        if (! empty($data['course_day_id'])) {
            $this->academy->inschrijven(CourseDay::findOrFail($data['course_day_id']), $user);
        }

        return back()->with('melding', "{$user->name} is uitgenodigd.");
    }

    public function lesstof(): Response
    {
        return Inertia::render('team/academy/lesstof', [
            'lessons' => Lesson::orderBy('position')->orderBy('id')->get(['id', 'title', 'body', 'position', 'minutes']),
            'assignments' => Assignment::orderBy('position')->orderBy('id')->get(['id', 'title', 'description', 'criteria', 'position']),
        ]);
    }

    public function lesOpslaan(Request $request): RedirectResponse
    {
        Lesson::create($this->lesGegevens($request));

        return back()->with('melding', 'Les toegevoegd.');
    }

    public function lesBijwerken(Request $request, Lesson $les): RedirectResponse
    {
        $les->update($this->lesGegevens($request));

        return back()->with('melding', 'Les bijgewerkt.');
    }

    public function lesVerwijderen(Lesson $les): RedirectResponse
    {
        $les->delete();

        return back()->with('melding', 'Les verwijderd.');
    }

    public function opdrachtOpslaan(Request $request): RedirectResponse
    {
        Assignment::create($this->opdrachtGegevens($request));

        return back()->with('melding', 'Opdracht toegevoegd.');
    }

    public function opdrachtBijwerken(Request $request, Assignment $opdracht): RedirectResponse
    {
        $opdracht->update($this->opdrachtGegevens($request));

        return back()->with('melding', 'Opdracht bijgewerkt.');
    }

    public function opdrachtVerwijderen(Assignment $opdracht): RedirectResponse
    {
        if ($opdracht->submissions()->exists()) {
            throw ValidationException::withMessages(['opdracht' => 'Er zijn al inzendingen voor deze opdracht.']);
        }
        $opdracht->delete();

        return back()->with('melding', 'Opdracht verwijderd.');
    }

    private function dagGegevens(Request $request, ?CourseDay $dag = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'date' => array_merge(['required', 'date'], $dag ? [] : ['after_or_equal:today']),
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'location_name' => ['required', 'string', 'max:190'],
            'address' => ['nullable', 'string', 'max:190'],
            'city' => ['required', 'string', 'max:120'],
            'max_participants' => ['required', 'integer', 'between:1,500'],
            'teacher_name' => ['nullable', 'string', 'max:120'],
            'price_euro' => ['required', 'numeric', 'between:0,10000'],
            'exam_required' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['price_cents'] = (int) round($data['price_euro'] * 100);
        $data['exam_required'] = $request->boolean('exam_required');
        unset($data['price_euro']);

        return $data;
    }

    private function lesGegevens(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:50000'],
            'position' => ['nullable', 'integer', 'between:0,999'],
            'minutes' => ['nullable', 'integer', 'between:1,600'],
        ]) + ['position' => 0];
    }

    /** Beoordelingslijst uit een tekstvak: één criterium per regel, "Naam: uitleg". */
    private function opdrachtGegevens(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'description' => ['required', 'string', 'max:5000'],
            'criteria' => ['required', 'string', 'max:3000'],
            'position' => ['nullable', 'integer', 'between:0,999'],
        ]);
        $data['criteria'] = collect(preg_split('/\R/', $data['criteria']))->map(fn ($r) => trim($r))->filter()
            ->map(fn ($r) => ['label' => trim(Str::before($r, ':')), 'hint' => Str::contains($r, ':') ? trim(Str::after($r, ':')) : ''])->values()->all();
        $data['position'] ??= 0;

        return $data;
    }

    /** Alleen bekende criteria, in de volgorde van de lijst; elk criterium moet een cijfer hebben. */
    private function scores(array $invoer, array $labels): array
    {
        $scores = [];
        foreach ($labels as $label) {
            if (! isset($invoer[$label])) {
                throw ValidationException::withMessages(['scores' => "Geef een cijfer voor {$label}."]);
            }
            $scores[$label] = (int) $invoer[$label];
        }

        return $scores;
    }
}
