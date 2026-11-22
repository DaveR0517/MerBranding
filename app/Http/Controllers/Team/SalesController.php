<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\LeadFase;
use App\Models\Uitnodigingslink;
use App\Services\Melder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sales-pipeline: kennismakingen van ondernemers per fase, met eigenaar, volgende actie en notities.
 * Klant worden gaat via het bestaande formulier Bedrijf toevoegen (team.bedrijven met lead_id): dat zet de lead op klant.
 */
class SalesController extends Controller
{
    public const RECHT = 'sales.beheren';

    public function __construct(private Melder $melder) {}

    public function index(): Response
    {
        Gate::authorize(self::RECHT);
        $leads = Lead::where('type', Lead::TYPE_ONDERNEMER)->latest()->limit(500)->get();
        $fases = LeadFase::with('owner')->whereIn('lead_id', $leads->pluck('id'))->get()->keyBy('lead_id');
        $historie = ActivityLog::with('user')->where('subject_type', (new Lead)->getMorphClass())->whereIn('subject_id', $leads->pluck('id'))
            ->latest('id')->get()->groupBy('subject_id');
        $codes = $leads->pluck('data.referral_code')->filter()->unique();
        $uitnodigers = Uitnodigingslink::with('user.companies')->whereIn('code', $codes)->get()->keyBy('code');

        return Inertia::render('team/communicatie/sales', [
            'stages' => collect(LeadFase::FASES)->map(fn ($naam, $key) => ['key' => $key, 'name' => $naam])->values(),
            'team' => $this->melder->team(self::RECHT)->sortBy('name')->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'leads' => $leads->map(function (Lead $l) use ($fases, $historie, $uitnodigers) {
                $fase = $fases[$l->id] ?? null;
                $code = $l->data['referral_code'] ?? null;
                $uitnodiger = $code ? $uitnodigers[$code] ?? null : null;

                return [
                    'id' => $l->id,
                    'name' => $l->name,
                    'company' => $l->company,
                    'email' => $l->email,
                    'phone' => $l->phone,
                    'city' => $l->city,
                    'message' => $l->message,
                    'created_at' => $l->created_at->toIso8601String(),
                    'stage' => $l->status === 'klant' ? 'klant' : ($fase->stage ?? 'nieuw'),
                    'is_customer' => $l->status === 'klant',
                    'owner' => $fase?->owner ? ['id' => $fase->owner->id, 'name' => $fase->owner->name] : null,
                    'next_action' => $fase?->next_action,
                    'next_action_on' => $fase?->next_action_on?->toDateString(),
                    'overdue' => $fase?->next_action_on?->isBefore(today()) ?? false,
                    'source' => $l->data['bron'] ?? ($uitnodiger ? 'Uitnodigingslink' : 'Website'),
                    'invited_by' => $uitnodiger ? trim($uitnodiger->user->name.($uitnodiger->user->companies->first() ? ', '.$uitnodiger->user->companies->first()->name : '')) : null,
                    'history' => ($historie[$l->id] ?? collect())->map(fn (ActivityLog $a) => [
                        'id' => $a->id,
                        'at' => $a->created_at->toIso8601String(),
                        'user' => $a->user?->name,
                        'event' => $a->event,
                        'text' => match ($a->event) {
                            'sales_fase' => 'Fase: '.(LeadFase::FASES[$a->from_status] ?? $a->from_status).' naar '.(LeadFase::FASES[$a->to_status] ?? $a->to_status).'.',
                            'sales_notitie' => $a->data['tekst'] ?? '',
                            'sales_toegevoegd' => 'Lead toegevoegd.',
                            'sales_actie' => 'Volgende actie: '.($a->data['actie'] ?? 'geen').'.',
                            default => $a->reason ?? $a->event,
                        },
                    ])->values(),
                ];
            })->values(),
        ]);
    }

    public function toevoegen(Request $request): RedirectResponse
    {
        Gate::authorize(self::RECHT);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);
        $lead = Lead::create($data + ['type' => Lead::TYPE_ONDERNEMER, 'status' => 'nieuw', 'data' => ['bron' => 'Handmatig']]);
        LeadFase::create(['lead_id' => $lead->id, 'stage' => 'nieuw', 'owner_id' => $request->user()->id]);
        $this->log($lead, $request, 'sales_toegevoegd');

        return back()->with('melding', 'De lead is toegevoegd.');
    }

    public function fase(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize(self::RECHT);
        $this->alleenOndernemer($lead);
        $data = $request->validate(['stage' => ['required', Rule::in(array_keys(LeadFase::FASES))]]);
        if ($data['stage'] === 'klant' && $lead->status !== 'klant') {
            throw ValidationException::withMessages(['stage' => 'Maak de klant aan met Bedrijf toevoegen; dan staat de lead vanzelf op Klant.']);
        }
        if ($lead->status === 'klant' && $data['stage'] !== 'klant') {
            throw ValidationException::withMessages(['stage' => 'Deze lead is al klant.']);
        }

        $fase = LeadFase::firstOrNew(['lead_id' => $lead->id], ['stage' => 'nieuw']);
        $van = $fase->stage;
        if ($van !== $data['stage']) {
            $fase->fill(['stage' => $data['stage'], 'owner_id' => $fase->owner_id ?? $request->user()->id])->save();
            $this->log($lead, $request, 'sales_fase', [], $van, $data['stage']);
        }

        return back()->with('melding', "{$lead->name} staat nu op ".LeadFase::FASES[$data['stage']].'.');
    }

    public function bijwerken(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize(self::RECHT);
        $this->alleenOndernemer($lead);
        $data = $request->validate([
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'next_action' => ['nullable', 'string', 'max:190'],
            'next_action_on' => ['nullable', 'date'],
        ]);
        $fase = LeadFase::firstOrNew(['lead_id' => $lead->id], ['stage' => 'nieuw']);
        $oudeActie = [$fase->next_action, $fase->next_action_on?->toDateString()];
        $fase->fill($data)->save();
        if ($oudeActie !== [$fase->next_action, $fase->next_action_on?->toDateString()]) {
            $this->log($lead, $request, 'sales_actie', ['actie' => trim(($data['next_action'] ?? '').' '.($fase->next_action_on?->translatedFormat('D j M') ?? '')) ?: 'geen']);
        }

        return back()->with('melding', 'De lead is bijgewerkt.');
    }

    public function notitie(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize(self::RECHT);
        $this->alleenOndernemer($lead);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']], ['body.required' => 'Schrijf eerst een notitie.']);
        $this->log($lead, $request, 'sales_notitie', ['tekst' => $data['body']]);

        return back()->with('melding', 'De notitie is opgeslagen.');
    }

    private function alleenOndernemer(Lead $lead): void
    {
        abort_unless($lead->isOndernemer(), 404);
    }

    private function log(Lead $lead, Request $request, string $event, array $data = [], ?string $van = null, ?string $naar = null): void
    {
        ActivityLog::create([
            'user_id' => $request->user()->id, 'subject_type' => $lead->getMorphClass(), 'subject_id' => $lead->id,
            'event' => $event, 'from_status' => $van, 'to_status' => $naar, 'data' => $data ?: null,
        ]);
    }
}
