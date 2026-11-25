<?php

use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Creator;
use App\Models\CreditLedger;
use App\Models\Invoice;
use App\Models\Payout;
use App\Models\PayoutRun;
use App\Models\Status;
use App\Models\User;
use App\Notifications\PlatformMelding;
use App\Services\Geld;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-23 10:00')); // maandag
    $this->seed(DemoSeeder::class);
    $this->app_url = 'http://app.mebranding.test';
    $this->wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
    $this->bloesem = Company::where('name', 'Lunchroom Bloesem')->first();
    $this->geld = app(Geld::class);

    $this->financieel = User::factory()->create(['email' => 'fin@demo.mebranding.nl']);
    $this->financieel->assignRole('Financieel');

    // Opeisbare vergoeding zoals de keten hem bij Live aanmaakt.
    $this->vergoeding = function (Creator $creator, string $nummer, string $runOn, int $bedrag = 16000): Payout {
        $c = Collaboration::create([
            'number' => $nummer, 'company_id' => $this->bloesem->id, 'location_id' => $this->bloesem->locations()->value('id'), 'creator_id' => $creator->id,
            'title' => "Klus {$nummer}", 'briefing' => 'Test', 'status' => Status::Live, 'points' => 8, 'creator_level' => $creator->level,
            'fee_cents' => $bedrag, 'live_at' => now(), 'measure_until' => now()->addDays(14),
        ]);

        return Payout::create(['creator_id' => $creator->id, 'collaboration_id' => $c->id, 'amount_cents' => $bedrag, 'run_on' => $runOn]);
    };
});

test('betaalrun: alleen opeisbare vergoedingen tot en met vrijdag met complete uitbetaalgegevens; export en uitbetaald', function () {
    $sophie = ($this->wie)('sophie')->creator;
    $sophie->forceFill(['payout_details' => 'NL00 TEST 0000 0000 01', 'vat_status' => 'KOR'])->save();
    $zonderGegevens = Creator::create(['user_id' => User::factory()->create(['name' => 'Daan Hoekstra'])->id, 'level' => 'platinum', 'status' => 'actief']);

    $mee = ($this->vergoeding)($sophie, 'P-1', '2026-11-27');
    $ingehouden = ($this->vergoeding)($zonderGegevens, 'P-2', '2026-11-27', 12500);
    $volgendeWeek = ($this->vergoeding)($sophie, 'P-3', '2026-12-04');

    expect(fn () => $this->geld->samenstellen(CarbonImmutable::parse('2026-11-26')))->toThrow(ValidationException::class);

    // Zonder financieel.beheren mag samenstellen niet
    $this->actingAs(($this->wie)('ruben'))->post("{$this->app_url}/team/financieel/betaalruns", ['run_on' => '2026-11-27'])->assertForbidden();
    $this->actingAs($this->financieel)->post("{$this->app_url}/team/financieel/betaalruns", ['run_on' => '2026-11-27'])->assertSessionHasNoErrors();

    $run = PayoutRun::sole();
    expect($run->run_on->toDateString())->toBe('2026-11-27')
        ->and($run->total_cents)->toBe(16000)
        ->and($run->payouts()->pluck('id')->all())->toBe([$mee->id])
        ->and($this->geld->ingehouden()->pluck('id')->all())->toBe([$ingehouden->id])
        ->and($volgendeWeek->fresh()->payout_run_id)->toBeNull();

    // Opnieuw samenstellen voegt niets dubbel toe
    $this->geld->samenstellen(CarbonImmutable::parse('2026-11-27'));
    expect(PayoutRun::count())->toBe(1)->and($run->fresh()->total_cents)->toBe(16000);

    $csv = $this->actingAs($this->financieel)->get("{$this->app_url}/team/financieel/betaalruns/{$run->id}/export")->assertOk()->assertDownload('betaalrun-2026-11-27.csv')->getContent();
    expect($csv)->toContain('Sophie de Vries')->toContain('160,00')->toContain('NL00 TEST')->not->toContain('Daan Hoekstra');

    $this->actingAs($this->financieel)->post("{$this->app_url}/team/financieel/betaalruns/{$run->id}/uitbetaald")->assertSessionHasNoErrors();
    expect($run->fresh()->status)->toBe('uitbetaald')
        ->and($mee->fresh()->status)->toBe('uitbetaald')
        ->and($ingehouden->fresh()->status)->toBe('opeisbaar')
        ->and(ActivityLog::where('event', 'vergoeding_uitbetaald')->where('subject_id', $mee->collaboration_id)->exists())->toBeTrue();
    Notification::assertSentTo($sophie->user, PlatformMelding::class, fn ($n) => $n->template->key === 'c-uitbetaling-verstuurd' && str_contains($n->vars['bedrag'], '160,00'));

    // Een uitbetaalde run kan niet nog eens
    expect(fn () => $this->geld->markeerUitbetaald($run->fresh(), $this->financieel))->toThrow(ValidationException::class);

    $this->actingAs(($this->wie)('ruben'))->get("{$this->app_url}/team/financieel")->assertForbidden();
    $this->actingAs($this->financieel)->get("{$this->app_url}/team/financieel")->assertOk();
});

test('op de 1e van de maand: tegoed toekennen en factuur maken, eenmalig, met melding o-factuur', function () {
    $sanne = ($this->wie)('sanne');
    $saldoVoor = $this->bloesem->availablePoints(); // 20 punten uit november (DemoSeeder)

    $this->travelTo(CarbonImmutable::parse('2026-12-01 06:10'));
    expect($this->geld->maandelijks())->toBe(1);

    $f = Invoice::sole();
    expect($f->company_id)->toBe($this->bloesem->id)
        ->and($f->number)->toBe('MB-2026-0001')
        ->and($f->period_start->toDateString())->toBe('2026-12-01')
        ->and($f->due_on->toDateString())->toBe('2026-12-15')
        ->and([$f->subtotal_cents, $f->vat_cents, $f->total_cents])->toBe([79500, 16695, 96195])
        ->and($f->status)->toBe('openstaand')
        ->and($this->bloesem->availablePoints())->toBe($saldoVoor + 20) // VOORSTEL: ongebruikt tegoed gaat mee
        ->and(CreditLedger::where('kind', 'toekennen')->latest('id')->first()->reason)->toContain('december 2026');
    Notification::assertSentTo($sanne, PlatformMelding::class, fn ($n) => $n->template->key === 'o-factuur' && str_contains($n->url, "/ondernemer/facturen/{$f->id}"));

    // Nog een keer draaien: niets dubbel
    expect($this->geld->maandelijks())->toBe(0)->and(Invoice::count())->toBe(1)->and($this->bloesem->availablePoints())->toBe($saldoVoor + 20);

    // De ondernemer opent zijn factuur; een ander bedrijf niet
    $this->actingAs($sanne)->get("{$this->app_url}/ondernemer/facturen/{$f->id}")->assertOk()->assertSee('MB-2026-0001')->assertSee('€ 961,95')->assertSee('[KvK-nummer]');
    $ander = Company::create(['name' => 'Ander']);
    $piet = User::factory()->create();
    $piet->assignRole('Ondernemer Eigenaar');
    $ander->users()->attach($piet->id, ['role' => 'eigenaar']);
    $this->actingAs($piet)->get("{$this->app_url}/ondernemer/facturen/{$f->id}")->assertForbidden();

    // Instelling 'vervallen': het positieve saldo vervalt bij de start van de nieuwe maand
    config(['geld.unused_credit' => 'vervallen']);
    $this->travelTo(CarbonImmutable::parse('2027-01-01 06:10'));
    $this->geld->maandelijks();
    expect($this->bloesem->availablePoints())->toBe(20)
        ->and(Invoice::where('number', 'MB-2027-0001')->exists())->toBeTrue();
});

test('herinneringen de dag na de vervaldatum en 7 dagen later, elk één keer, ook naar de accountmanager', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 06:10'));
    $this->geld->maandelijks();
    $f = Invoice::sole();
    $sanne = ($this->wie)('sanne');
    $eva = ($this->wie)('eva');
    $herinneringen = fn (User $u) => Notification::sent($u, PlatformMelding::class)->filter(fn ($n) => $n->template->key === 'o-factuur-vervallen')->count();

    $this->travelTo(CarbonImmutable::parse('2026-12-15 09:05')); // vervaldatum zelf
    expect($this->geld->herinneringen())->toBe(0);
    $this->travelTo(CarbonImmutable::parse('2026-12-16 09:05')); // dag na de vervaldatum
    expect($this->geld->herinneringen())->toBe(1)->and($this->geld->herinneringen())->toBe(0);
    expect($herinneringen($sanne))->toBe(1)->and($herinneringen($eva))->toBe(1);

    $this->travelTo(CarbonImmutable::parse('2026-12-22 09:05')); // 6 dagen later: nog niet
    expect($this->geld->herinneringen())->toBe(0);
    $this->travelTo(CarbonImmutable::parse('2026-12-23 09:05')); // 7 dagen na de eerste herinnering
    expect($this->geld->herinneringen())->toBe(1)->and($f->fresh()->reminders_sent)->toBe(2);
    $this->travelTo(CarbonImmutable::parse('2027-01-10 09:05'));
    expect($this->geld->herinneringen())->toBe(0);

    // Betaald gemarkeerd: geen herinneringen meer, logregel
    $this->actingAs($this->financieel)->post("{$this->app_url}/team/financieel/facturen/{$f->id}/betaald")->assertSessionHasNoErrors();
    expect($f->fresh()->status)->toBe('betaald')->and(ActivityLog::where('event', 'factuur_betaald')->exists())->toBeTrue();
});

test('de jobs staan in de planner', function () {
    $namen = collect(app(Schedule::class)->events())->map(fn ($e) => $e->description)->all();

    expect($namen)->toContain('maandelijks-tegoed-en-factuur')->toContain('factuurherinneringen')->toContain('betaalrun-samenstellen');
});
