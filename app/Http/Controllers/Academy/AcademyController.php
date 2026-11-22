<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Http\Presenters\AcademyPresenter as P;
use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\CourseDay;
use App\Models\Creator;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Submission;
use App\Models\User;
use App\Services\Academy;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Omgeving academy (student): Mijn academy, inschrijven, lessen, opdrachten, check-in, examen, certificaat en Word creator. */
class AcademyController extends Controller
{
    public function __construct(private Academy $academy) {}

    public function overzicht(Request $request): Response
    {
        $user = $request->user();
        $inschrijvingen = Enrollment::with(['courseDay', 'exam.submissions', 'exam.assessor', 'certificate'])
            ->where('user_id', $user->id)->where('status', '!=', Enrollment::AFGEMELD)->get()
            ->sortBy(fn (Enrollment $e) => ($e->courseDay->date->lt(today()) ? '1' : '0').$e->courseDay->date->toDateString())->values(); // komende dagen eerst
        $mijnDagen = $inschrijvingen->pluck('course_day_id')->all();
        $gedaan = LessonProgress::where('user_id', $user->id)->pluck('lesson_id')->all();
        $creator = Creator::where('user_id', $user->id)->first();

        return Inertia::render('academy/overzicht', [
            'name' => $user->name,
            'enrollments' => $inschrijvingen->map(fn (Enrollment $e) => $this->inschrijving($e))->values(),
            'course_days' => CourseDay::where('status', CourseDay::OPEN)->whereDate('date', '>=', today())->whereNotIn('id', $mijnDagen)
                ->orderBy('date')->limit(6)->get()->map(fn (CourseDay $d) => P::dag($d))->values(),
            'lessons' => Lesson::orderBy('position')->orderBy('id')->get(['id', 'title', 'minutes'])
                ->map(fn (Lesson $l) => ['id' => $l->id, 'title' => $l->title, 'minutes' => $l->minutes, 'done' => in_array($l->id, $gedaan, true)])->values(),
            'assignments' => Assignment::orderBy('position')->orderBy('id')->get()->map(function (Assignment $a) use ($user) {
                $laatste = Submission::where('assignment_id', $a->id)->where('user_id', $user->id)->latest('id')->first();

                return ['id' => $a->id, 'title' => $a->title, 'submitted' => (bool) $laatste, 'assessed' => (bool) $laatste?->assessed_at];
            })->values(),
            'creator' => $creator ? ['status' => $creator->status, 'level' => $creator->levelName()] : null,
            'can_become_creator' => ! $creator && Certificate::where('user_id', $user->id)->exists(),
        ]);
    }

    public function cursusdag(Request $request, CourseDay $dag): Response
    {
        $user = $request->user();
        $mijn = Enrollment::with(['courseDay', 'exam.submissions', 'certificate'])->where('course_day_id', $dag->id)->where('user_id', $user->id)
            ->where('status', '!=', Enrollment::AFGEMELD)->first();
        $vorige = Enrollment::where('user_id', $user->id)->latest('id')->first();

        return Inertia::render('academy/cursusdag', [
            'day' => P::dag($dag),
            'enrollment' => $mijn ? $this->inschrijving($mijn) : null,
            'prefill' => [
                'phone' => $vorige?->phone ?? '', 'city' => $vorige?->city ?? '', 'tiktok' => $vorige?->tiktok ?? '',
                'instagram' => $vorige?->instagram ?? '', 'birthdate' => $vorige?->birthdate?->toDateString() ?? '',
            ],
            'min_age' => (int) config('academy.min_age'),
        ]);
    }

    public function inschrijven(Request $request, CourseDay $dag): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'city' => ['required', 'string', 'max:120'],
            'tiktok' => ['nullable', 'string', 'max:60'],
            'instagram' => ['nullable', 'string', 'max:60'],
            'birthdate' => ['required', 'date', 'before_or_equal:'.now()->subYears((int) config('academy.min_age'))->toDateString()],
            'akkoord' => ['accepted'],
        ], [
            'birthdate.before_or_equal' => 'De minimumleeftijd is '.config('academy.min_age').' jaar.',
            'akkoord.accepted' => 'Ga akkoord met de voorwaarden en de annuleringsregeling.',
        ]);

        $inschrijving = $this->academy->inschrijven($dag, $request->user(), $data);

        return back()->with('melding', $inschrijving->status === Enrollment::WACHTLIJST
            ? "Deze dag is vol. Je staat op plek {$inschrijving->plek()} van de wachtlijst."
            : 'Je bent ingeschreven. Je ticket staat in Mijn academy.');
    }

    public function afmelden(Request $request, Enrollment $inschrijving): RedirectResponse
    {
        $this->eigen($request, $inschrijving);
        $this->academy->afmelden($inschrijving, $request->user());

        return back()->with('melding', 'Je bent afgemeld.');
    }

    /** "Ik ben er": alleen op de dag zelf. */
    public function inchecken(Request $request, Enrollment $inschrijving): RedirectResponse
    {
        $this->eigen($request, $inschrijving);
        $this->academy->inchecken($inschrijving, $request->user(), 'zelf ingecheckt');

        return back()->with('melding', 'Je bent ingecheckt. Fijne dag!');
    }

    public function les(Request $request, Lesson $les): Response
    {
        $lessen = Lesson::orderBy('position')->orderBy('id')->get(['id', 'title', 'minutes']);
        $gedaan = LessonProgress::where('user_id', $request->user()->id)->pluck('lesson_id')->all();
        $index = $lessen->search(fn ($l) => $l->id === $les->id);

        return Inertia::render('academy/les', [
            'lesson' => ['id' => $les->id, 'title' => $les->title, 'minutes' => $les->minutes, 'html' => $les->html(), 'done' => in_array($les->id, $gedaan, true)],
            'lessons' => $lessen->map(fn ($l) => ['id' => $l->id, 'title' => $l->title, 'minutes' => $l->minutes, 'done' => in_array($l->id, $gedaan, true)])->values(),
            'position' => $index + 1,
            'previous_id' => $lessen[$index - 1]->id ?? null,
            'next_id' => $lessen[$index + 1]->id ?? null,
        ]);
    }

    public function lesKlaar(Request $request, Lesson $les): RedirectResponse
    {
        LessonProgress::firstOrCreate(['lesson_id' => $les->id, 'user_id' => $request->user()->id], ['completed_at' => now()]);

        return back()->with('melding', 'Les afgerond.');
    }

    public function opdracht(Request $request, Assignment $opdracht): Response
    {
        return Inertia::render('academy/opdracht', [
            'assignment' => ['id' => $opdracht->id, 'title' => $opdracht->title, 'description' => $opdracht->description, 'criteria' => $opdracht->criteria],
            'submissions' => Submission::with('assessor')->where('assignment_id', $opdracht->id)->where('user_id', $request->user()->id)
                ->latest('id')->get()->map(fn (Submission $s) => P::inzending($s))->values(),
        ]);
    }

    public function opdrachtIndienen(Request $request, Assignment $opdracht): RedirectResponse
    {
        $data = $request->validate([
            'link' => ['nullable', 'url', 'max:500', 'required_without:video'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:'.config('academy.video_max_kb'), 'required_without:link'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['link.required_without' => 'Voeg een link of een video toe.', 'video.required_without' => 'Voeg een link of een video toe.']);

        Submission::create(['user_id' => $request->user()->id, 'assignment_id' => $opdracht->id, 'link' => $data['link'] ?? null, 'note' => $data['note'] ?? null]
            + $this->bewaar($request->file('video'), $request->user()));

        return back()->with('melding', 'Ingediend. Je krijgt feedback van je docent.');
    }

    public function examen(Request $request, Exam $examen): Response
    {
        $this->eigen($request, $examen->enrollment);
        $examen->load(['submissions', 'assessor', 'enrollment.courseDay', 'enrollment.certificate']);

        return Inertia::render('academy/examen', [
            'exam' => P::examen($examen),
            'day' => P::dag($examen->enrollment->courseDay),
            'certificate' => P::certificaat($examen->enrollment->certificate),
            'criteria' => P::examenCriteria(),
            'videos' => (int) config('academy.exam_videos'),
            'pass_average' => (float) config('academy.pass_average'),
        ]);
    }

    /** Praktijkexamen inleveren: per video een bestand of een link, plus de twee verklaringen. */
    public function examenInleveren(Request $request, Exam $examen): RedirectResponse
    {
        $this->eigen($request, $examen->enrollment);
        if (! $examen->kanInleveren()) {
            throw ValidationException::withMessages(['examen' => 'Dit examen kun je nu niet inleveren.']);
        }
        $aantal = (int) config('academy.exam_videos');
        $request->validate([
            'items' => ['required', 'array', "size:{$aantal}"],
            'items.*.link' => ['nullable', 'url', 'max:500'],
            'items.*.video' => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:'.config('academy.video_max_kb')],
            'eigen_werk' => ['accepted'],
            'toestemming' => ['accepted'],
        ], [
            'eigen_werk.accepted' => 'Bevestig dat dit je eigen werk is.',
            'toestemming.accepted' => 'Bevestig dat iedereen in beeld toestemming gaf.',
        ]);
        foreach (range(0, $aantal - 1) as $i) {
            if (! $request->filled("items.{$i}.link") && ! $request->hasFile("items.{$i}.video")) {
                throw ValidationException::withMessages(["items.{$i}" => 'Voeg voor video '.($i + 1).' een bestand of een link toe.']);
            }
        }

        $poging = $examen->status === Exam::VERBETEREN ? $examen->attempt + 1 : $examen->attempt;
        DB::transaction(function () use ($request, $examen, $aantal, $poging) {
            foreach (range(0, $aantal - 1) as $i) {
                Submission::create([
                    'user_id' => $request->user()->id, 'exam_id' => $examen->id, 'attempt' => $poging, 'label' => 'Video '.($i + 1),
                    'link' => $request->input("items.{$i}.link") ?: null,
                ] + $this->bewaar($request->file("items.{$i}.video"), $request->user()));
            }
            $examen->update(['status' => Exam::INGELEVERD, 'attempt' => $poging, 'submitted_at' => now()]);
        });

        return back()->with('melding', 'Je examen is ingeleverd. Je krijgt een melding zodra de beoordeling klaar is.');
    }

    /** Certificaat als afdrukbare pagina in huisstijl. Met ?download=1 als bestand. */
    public function certificaat(Request $request, Certificate $certificaat): HttpResponse
    {
        abort_unless($certificaat->user_id === $request->user()->id, 403);
        $certificaat->load(['enrollment.courseDay', 'enrollment.exam', 'user']);
        $html = view('academy.certificaat', ['certificaat' => $certificaat, 'afdrukken' => ! $request->boolean('download')])->render();

        return $request->boolean('download')
            ? response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"certificaat-{$certificaat->number}.html\""])
            : response($html);
    }

    public function wordCreator(Request $request): RedirectResponse
    {
        $this->academy->wordCreator($request->user());

        return back()->with('melding', 'Je aanmelding als creator is binnen. We screenen je profiel binnen '.config('academy.screening_workdays').' werkdagen.');
    }

    public function video(Request $request, Submission $inzending): HttpResponse
    {
        abort_unless($inzending->user_id === $request->user()->id, 403);

        return P::video($inzending);
    }

    private function inschrijving(Enrollment $e): array
    {
        $d = $e->courseDay;
        $heeftTicket = in_array($e->status, [Enrollment::INGESCHREVEN, Enrollment::AANWEZIG], true) && ! $d->date->lt(today());

        return [
            'id' => $e->id, 'status' => $e->status, 'status_name' => P::statusNaam($e->status), 'place' => $e->plek(),
            'paid' => $e->paid_status === Enrollment::BETAALD, 'paid_at' => $e->paid_at?->toIso8601String(),
            'offer_expires_at' => $e->offer_expires_at?->toIso8601String(), 'checked_in_at' => $e->checked_in_at?->toIso8601String(),
            'can_check_in' => $e->status === Enrollment::INGESCHREVEN && $d->isVandaag() && $d->status === CourseDay::OPEN,
            'can_cancel' => in_array($e->status, [Enrollment::INGESCHREVEN, Enrollment::WACHTLIJST], true) && $d->status === CourseDay::OPEN,
            'qr_svg' => $heeftTicket && ! $e->checked_in_at ? $this->qr(route('team.academy.checkin', $e->checkin_code)) : null,
            'day' => P::dag($d),
            'exam' => P::examen($e->exam),
            'certificate' => P::certificaat($e->certificate),
        ];
    }

    /** QR-code als SVG: het team scant hem en checkt de deelnemer daarmee in. */
    private function qr(string $url): string
    {
        $svg = (new Writer(new ImageRenderer(
            new RendererStyle(200, 1, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(46, 43, 72))),
            new SvgImageBackEnd,
        )))->writeString($url);

        return trim(substr($svg, strpos($svg, "\n") + 1));
    }

    /** @return array{disk?: string, path?: string, mime?: string} */
    private function bewaar(?UploadedFile $video, User $user): array
    {
        if (! $video) {
            return [];
        }
        $disk = config('academy.video_disk');

        return ['disk' => $disk, 'path' => $video->store("academy/{$user->id}", $disk), 'mime' => $video->getMimeType()];
    }

    private function eigen(Request $request, Enrollment $inschrijving): void
    {
        abort_unless($inschrijving->user_id === $request->user()->id, 403);
    }
}
