<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CreatorProfiel;
use App\Models\Lead;
use App\Models\ScreeningAanmelding;
use App\Models\User;
use App\Services\Screening;
use App\Services\Uitnodiging;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Screening van aanmeldingen als creator door het team (recht creators.screenen). */
class ScreeningController extends Controller
{
    private const FILTERS = ['nieuw', 'aanvulling', 'afgewezen', 'creator'];

    public function __construct(private Screening $screening, private Uitnodiging $uitnodiging) {}

    public function index(Request $request): Response
    {
        Gate::authorize('creators.screenen');
        $filter = in_array($request->query('status'), self::FILTERS, true) ? $request->query('status') : 'nieuw';
        $aantallen = Lead::where('type', Lead::TYPE_CREATOR)->whereIn('status', self::FILTERS)
            ->selectRaw('status, count(*) as aantal')->groupBy('status')->pluck('aantal', 'status');
        $taken = ScreeningAanmelding::with('task')->get()->keyBy('lead_id');

        return Inertia::render('team/screening/index', [
            'filter' => $filter,
            'counts' => collect(self::FILTERS)->mapWithKeys(fn ($s) => [$s => (int) ($aantallen[$s] ?? 0)]),
            'leads' => Lead::where('type', Lead::TYPE_CREATOR)->where('status', $filter)
                ->orderBy($filter === 'nieuw' ? 'created_at' : 'updated_at', $filter === 'nieuw' ? 'asc' : 'desc')->limit(200)->get()
                ->map(fn (Lead $l) => $this->kort($l, $taken[$l->id] ?? null))->values(),
        ]);
    }

    public function toon(Lead $lead): Response
    {
        Gate::authorize('creators.screenen');
        abort_unless($lead->type === Lead::TYPE_CREATOR, 404);
        $screening = ScreeningAanmelding::with(['task', 'beslisser'])->firstWhere('lead_id', $lead->id);

        return Inertia::render('team/screening/detail', [
            'lead' => $this->kort($lead, $screening) + [
                'phone' => $lead->phone, 'message' => $lead->message,
                'consent_at' => $lead->consent_at?->toIso8601String(), 'consent_version' => $lead->consent_version,
                'data' => $this->velden($lead->data ?? []),
            ],
            'decision' => $screening?->decision ? [
                'decision' => $screening->decision, 'reason' => $screening->reason, 'level' => $screening->level ? config("mebranding.levels.{$screening->level}.name") : null,
                'by' => $screening->beslisser?->name, 'at' => $screening->decided_at?->toIso8601String(),
            ] : null,
            'history' => ActivityLog::with('user')->where('subject_type', $lead->getMorphClass())->where('subject_id', $lead->id)->latest('id')->get()
                ->map(fn (ActivityLog $a) => ['event' => $a->event, 'reason' => $a->reason, 'by' => $a->user?->name, 'at' => $a->created_at?->toIso8601String()])->values(),
            'levels' => collect(config('mebranding.levels'))->map(fn ($l, $k) => ['value' => $k, 'name' => $l['name']])->values(),
            'open' => in_array($lead->status, ['nieuw', 'aanvulling'], true),
            // Opnieuw uitnodigen: per mail als die werkt, anders de link in beeld (App\Services\Uitnodiging).
            'opnieuw' => $this->creatorAccount($lead) ? ['mail' => Uitnodiging::mailWerkt()] : null,
        ]);
    }

    public function goedkeuren(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('creators.screenen');
        $data = $request->validate([
            'level' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:1000'],
        ], ['reason.required' => 'Schrijf kort op waarom je goedkeurt.']);
        $creator = $this->screening->goedkeuren($lead, $request->user(), $data['level'], $data['reason']);

        return to_route('team.screening.toon', $lead)->with('melding', "{$lead->name} is goedgekeurd op {$creator->levelName()}.");
    }

    /** Uitnodiging opnieuw: dezelfde welkomstmail met een verse link (eerdere links vervallen); zonder mail de link in beeld. */
    public function uitnodigingslink(Lead $lead): RedirectResponse
    {
        Gate::authorize('creators.screenen');
        $user = $this->creatorAccount($lead);
        abort_unless($user, 404);
        $this->uitnodiging->stuur($user, 'c-welkom', ['niveau' => $user->creator->levelName()]);

        return back()->with('melding', Uitnodiging::mailWerkt() ? "Uitnodiging opnieuw gemaild naar {$user->email}." : 'Nieuwe uitnodigingslink gemaakt. Eerdere links werken niet meer.');
    }

    private function creatorAccount(Lead $lead): ?User
    {
        if ($lead->type !== Lead::TYPE_CREATOR || $lead->status !== 'creator') {
            return null;
        }

        return User::where('email', Str::lower($lead->email))->whereHas('creator')->first();
    }

    public function aanvulling(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('creators.screenen');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], ['reason.required' => 'Schrijf wat er nog ontbreekt.']);
        $this->screening->aanvullingVragen($lead, $request->user(), $data['reason']);

        return to_route('team.screening.index')->with('melding', "We vragen {$lead->name} om een aanvulling.");
    }

    public function afwijzen(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('creators.screenen');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], ['reason.required' => 'Een afwijzing heeft altijd een reden.']);
        $this->screening->afwijzen($lead, $request->user(), $data['reason']);

        return to_route('team.screening.index')->with('melding', "De aanmelding van {$lead->name} is afgewezen.");
    }

    private function kort(Lead $l, ?ScreeningAanmelding $s): array
    {
        $data = $l->data ?? [];

        return [
            'id' => $l->id, 'name' => $l->name, 'email' => $l->email, 'city' => $l->city, 'status' => $l->status,
            'kind' => $data['soort'] ?? null,
            'channels' => collect(['tiktok', 'instagram', 'youtube', 'facebook', 'snapchat'])
                ->filter(fn ($k) => filled($data["{$k}-naam"] ?? null))
                ->map(fn ($k) => ['platform' => $k, 'handle' => (string) $data["{$k}-naam"], 'followers' => $data["{$k}-volgers"] ?? null, 'business' => (bool) ($data["{$k}-zakelijk"] ?? false)])
                ->values(),
            'created_at' => $l->created_at?->toIso8601String(),
            'due_at' => $s?->task?->closed_at === null ? $s?->task?->due_at?->toIso8601String() : null,
            'overdue' => $s?->task && ! $s->task->closed_at && $s->task->due_at?->isPast(),
        ];
    }

    /** Alle velden van het formulier, leesbaar. */
    private function velden(array $data): array
    {
        return collect($data)->map(function ($v, $k) {
            $k = (string) $k;
            $waarde = match (true) {
                $k === 'niches' => implode(', ', array_map(fn ($n) => CreatorProfiel::NICHES[$n] ?? (string) $n, (array) $v)),
                $k === 'soort' => CreatorProfiel::SOORTEN[$v] ?? (string) $v,
                str_ends_with($k, '-zakelijk') => filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 'ja' : 'nee',
                is_array($v) => implode(', ', array_map('strval', $v)),
                default => (string) $v,
            };

            return ['key' => $k, 'value' => $waarde];
        })->values()->all();
    }
}
