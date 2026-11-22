<?php

namespace App\Http\Presenters;

use App\Models\Certificate;
use App\Models\CourseDay;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Eén plek voor hoe cursusdagen, inschrijvingen, examens en inzendingen naar de schermen gaan (student en team). */
class AcademyPresenter
{
    public static function dag(CourseDay $d): array
    {
        $bezet = $d->bezet();

        return [
            'id' => $d->id, 'title' => $d->title, 'date' => $d->date->toDateString(), 'starts_at' => substr($d->starts_at, 0, 5), 'ends_at' => substr($d->ends_at, 0, 5),
            'location_name' => $d->location_name, 'address' => $d->address, 'city' => $d->city, 'teacher_name' => $d->teacher_name,
            'max_participants' => $d->max_participants, 'taken' => $bezet, 'free' => max(0, $d->max_participants - $bezet),
            'waitlist' => $d->enrollments()->where('status', Enrollment::WACHTLIJST)->count(),
            'price_cents' => $d->price_cents, 'exam_required' => $d->exam_required, 'status' => $d->status, 'notes' => $d->notes,
            'is_today' => $d->isVandaag(), 'is_past' => $d->date->lt(today()),
        ];
    }

    public static function certificaat(?Certificate $c): ?array
    {
        return $c ? ['id' => $c->id, 'number' => $c->number, 'issued_at' => $c->issued_at->toIso8601String(), 'check_url' => $c->controleUrl()] : null;
    }

    public static function examen(?Exam $e): ?array
    {
        if (! $e) {
            return null;
        }

        return [
            'id' => $e->id, 'status' => $e->status, 'attempt' => $e->attempt, 'due_at' => $e->due_at?->toIso8601String(),
            'submitted_at' => $e->submitted_at?->toIso8601String(), 'scores' => $e->scores, 'average' => $e->average,
            'feedback' => $e->feedback, 'assessor' => $e->assessor?->name, 'assessed_at' => $e->assessed_at?->toIso8601String(),
            'videos' => $e->submissions->where('attempt', $e->attempt)->sortBy('id')->map(fn (Submission $s) => self::inzending($s))->values(),
        ];
    }

    public static function inzending(Submission $s, string $omgeving = 'academy'): array
    {
        return [
            'id' => $s->id, 'label' => $s->label, 'link' => $s->link, 'note' => $s->note, 'attempt' => $s->attempt,
            'video_url' => $s->path ? route("{$omgeving}.inzending.video", $s) : null,
            'scores' => $s->scores, 'feedback' => $s->feedback, 'assessor' => $s->assessor?->name,
            'assessed_at' => $s->assessed_at?->toIso8601String(), 'created_at' => $s->created_at->toIso8601String(),
        ];
    }

    /** Beoordelingslijst van het praktijkexamen. */
    public static function examenCriteria(): array
    {
        return config('academy.exam_criteria');
    }

    public static function statusNaam(string $status): string
    {
        return [
            Enrollment::INGESCHREVEN => 'Ingeschreven', Enrollment::WACHTLIJST => 'Wachtlijst', Enrollment::AANWEZIG => 'Aanwezig',
            Enrollment::AFWEZIG => 'Niet verschenen', Enrollment::GESLAAGD => 'Geslaagd', Enrollment::AFGEMELD => 'Afgemeld',
        ][$status] ?? $status;
    }

    /** Video van een inzending afspelen (privé opslag). Wie mag kijken, bepaalt de controller. */
    public static function video(Submission $s): Response
    {
        abort_unless($s->path, 404);
        $disk = Storage::disk($s->disk);

        return $s->disk === 'local'
            ? response()->file($disk->path($s->path), ['Content-Type' => $s->mime ?? 'video/mp4', 'Cache-Control' => 'private, max-age=3600'])
            : $disk->response($s->path, basename($s->path), ['Content-Type' => $s->mime ?? 'video/mp4']);
    }
}
