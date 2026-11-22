<?php

namespace App\Http\Presenters;

use App\Models\Company;
use App\Models\CreditLedger;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Status;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use App\Models\VisitWindow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Gegevens voor de beheerschermen van een bedrijf. Eén vorm voor de ondernemer en het team. */
class BedrijfsbeheerPresenter
{
    public static function instellingen(Company $b): array
    {
        return ['self_confirm_visits' => $b->self_confirm_visits, 'owner_approves_content' => $b->owner_approves_content];
    }

    public static function vestigingen(Company $b): array
    {
        $lopend = $b->collaborations()->whereNotIn('status', [Status::Afgerond, Status::Geannuleerd, Status::NietVerschenen])
            ->selectRaw('location_id, count(*) as aantal')->groupBy('location_id')->pluck('aantal', 'location_id');

        return $b->locations()->with('visitWindows')->orderBy('name')->get()->map(fn (Location $l) => [
            'id' => $l->id, 'name' => $l->name, 'address' => $l->address, 'city' => $l->city, 'experience' => $l->experience,
            'active' => (int) ($lopend[$l->id] ?? 0),
            'windows' => $l->visitWindows->map(fn (VisitWindow $w) => [
                'weekday' => $w->weekday, 'starts_at' => substr($w->starts_at, 0, 5), 'ends_at' => substr($w->ends_at, 0, 5),
            ])->values(),
        ])->values()->all();
    }

    public static function gebruikers(Company $b): array
    {
        $open = DB::table('password_reset_tokens')->pluck('created_at', 'email');
        $geldig = (int) config('auth.passwords.users.expire', 10080);

        return $b->users()->orderByRaw("case when company_user.role = 'eigenaar' then 0 else 1 end")->orderBy('name')->get()
            ->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->pivot->role,
                'two_factor' => $u->two_factor_confirmed_at !== null,
                // Uitnodiging staat open zolang de link om een wachtwoord te kiezen nog niet is gebruikt.
                'invited_until' => isset($open[$u->email]) ? now()->parse($open[$u->email])->addMinutes($geldig)->toIso8601String() : null,
            ])->values()->all();
    }

    /** Grootboek met lopend saldo, nieuwste eerst. */
    public static function grootboek(Company $b, int $max = 200): array
    {
        $saldo = 0;
        $regels = $b->ledger()->with('collaboration:id,number')->oldest('id')->get()->map(function (CreditLedger $r) use (&$saldo) {
            $saldo += $r->points;

            return [
                'id' => $r->id, 'date' => $r->created_at->toIso8601String(), 'kind' => $r->kind, 'points' => $r->points,
                'reason' => $r->reason, 'collaboration' => $r->collaboration?->number, 'balance' => $saldo,
            ];
        });

        return $regels->reverse()->take($max)->values()->all();
    }

    public static function abonnement(Company $b): ?array
    {
        /** @var Subscription|null $s */
        $s = $b->subscriptions()->with('planVersion.plan')->latest('starts_on')->latest('id')->first();
        if (! $s) {
            return null;
        }

        return [
            'plan_id' => $s->planVersion->plan_id, 'name' => $s->planVersion->plan->name,
            'price_cents' => $s->planVersion->price_cents, 'points_per_month' => $s->planVersion->points_per_month,
            'is_proposal' => $s->planVersion->is_proposal, 'starts_on' => $s->starts_on->toDateString(),
            'cancels_on' => $s->cancels_on?->toDateString(), 'status' => $s->status,
        ];
    }

    public static function pakketten(): array
    {
        return Plan::orderBy('id')->get()->map(function (Plan $p) {
            $v = $p->currentVersion();

            return ['id' => $p->id, 'name' => $p->name, 'price_cents' => $v?->price_cents, 'points_per_month' => $v?->points_per_month, 'is_proposal' => $v?->is_proposal ?? true];
        })->filter(fn ($p) => $p['price_cents'] !== null)->values()->all();
    }

    /** Facturen komen in fase 6 (module D). Bestaat de tabel al, dan tonen we wat er staat. */
    public static function facturen(Company $b): ?array
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasColumn('invoices', 'company_id')) {
            return null;
        }

        return DB::table('invoices')->where('company_id', $b->id)->orderByDesc('id')->limit(50)->get()->map(fn ($f) => [
            'id' => $f->id,
            'number' => $f->number ?? (string) $f->id,
            'date' => $f->issued_on ?? $f->date ?? $f->created_at ?? null,
            'amount_cents' => $f->total_cents ?? $f->amount_cents ?? null,
            'status' => $f->status ?? null,
        ])->values()->all();
    }

    /** @return list<array{id: int, title: string, created_at: string}> */
    public static function openVerzoeken(Company $b): array
    {
        return Task::open()->where('company_id', $b->id)->where('type', 'abonnement')->latest()->get()
            ->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'created_at' => $t->created_at->toIso8601String()])->values()->all();
    }
}
