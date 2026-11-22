<?php

use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Creator;
use App\Models\Payout;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PlatformMelding;
use App\Services\Samenwerking;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->seed(DemoSeeder::class);
    $this->dienst = app(Samenwerking::class);
    $this->bloesem = Company::where('name', 'Lunchroom Bloesem')->first();
    $this->sanne = User::where('email', 'sanne@demo.mebranding.nl')->first();
    $this->eva = User::where('email', 'eva@demo.mebranding.nl')->first();
    $this->ruben = User::where('email', 'ruben@demo.mebranding.nl')->first();
    $this->marit = Creator::whereHas('user', fn ($q) => $q->where('email', 'marit@demo.mebranding.nl'))->first();
    $this->sophie = Creator::whereHas('user', fn ($q) => $q->where('email', 'sophie@demo.mebranding.nl'))->first();
});

function geldigeBijschrift(): string
{
    return 'Betaalde samenwerking met Lunchroom Bloesem. Heerlijke lunch!';
}

function doorloopTotLive(Samenwerking $d, Collaboration $c, Creator $creator, User $team, User $controle): Collaboration
{
    $d->uitnodigen($c, $creator, $team);
    $d->accepteren($c->fresh()->invitations()->first(), $creator->user, $creator->user->name, '127.0.0.1');

    $morgen = CarbonImmutable::tomorrow()->setTime(12, 0);
    $d->tijdvakKiezen($c->fresh(), $creator->user, $morgen);

    test()->travelTo($morgen->setTime(11, 55));
    $d->inchecken($c->fresh(), $creator->user);
    $d->klaarMetFilmen($c->fresh(), $creator->user);

    $bevestigd = ['label_platform' => true, 'label_beeld' => true];
    $d->indienen($c->fresh(), $creator->user, UploadedFile::fake()->create('v1.mp4', 500, 'video/mp4'), geldigeBijschrift(), $bevestigd);
    $d->feedbackSturen($c->fresh(), $controle, [['timecode' => 4, 'body' => 'Logo eerder in beeld']]);
    $d->indienen($c->fresh(), $creator->user, UploadedFile::fake()->create('v2.mp4', 500, 'video/mp4'), geldigeBijschrift(), $bevestigd);
    $d->goedkeuren($c->fresh(), $controle, CarbonImmutable::today()->addDay());
    $d->liveKoppelen($c->fresh(), $creator->user, 'instagram', 'https://www.instagram.com/p/voorbeeld/');

    return $c->fresh();
}

test('de hele keten met Marit (Bronze): geen geld, wel gratis bezoek', function () {
    expect($this->bloesem->availablePoints())->toBe(20);

    $c = $this->dienst->aanvragen($this->bloesem, $this->sanne, [
        'location_id' => $this->bloesem->locations()->first()->id, 'title' => 'Kerstlunch', 'briefing' => 'Laat de kerstlunch zien.',
        'creator_level' => 'bronze',
    ]);
    expect($c->status)->toBe(Status::Nieuw)
        ->and($this->bloesem->availablePoints())->toBe(17)
        ->and(Task::open()->where('type', 'koppelen')->count())->toBe(1);

    $c = doorloopTotLive($this->dienst, $c, $this->marit, $this->eva, $this->ruben);

    expect($c->status)->toBe(Status::Live)
        ->and($c->versions)->toHaveCount(2)
        ->and($c->approvedVersion->version)->toBe(2)
        ->and($this->bloesem->availablePoints())->toBe(17)
        ->and(Payout::count())->toBe(0)
        ->and(Task::open()->count())->toBe(0);

    // De uitnodiging aan Marit noemt een gratis bezoek en geen geldbedrag.
    Notification::assertSentTo($this->marit->user, PlatformMelding::class, function ($n) {
        return $n->template->key !== 'c-uitnodiging' || str_contains($n->vars['bedrag'], 'gratis bezoek (geen geldbedrag)');
    });
    // Sanne krijgt de goedkeuring als melding (en per mail).
    Notification::assertSentTo($this->sanne, PlatformMelding::class, fn ($n, $kanalen) => $n->template->key === 'o-goedgekeurd' && in_array('mail', $kanalen));

    $this->travel(15)->days();
    expect($this->dienst->rondAf())->toBe(1);
    $c->refresh();
    expect($c->status)->toBe(Status::Afgerond);

    $this->dienst->beoordelen($c, $this->sanne, 5, 'Top!');
    Notification::assertSentTo($this->marit->user, PlatformMelding::class, fn ($n) => $n->template->key === 'c-beoordeling');

    $statussen = ActivityLog::where('event', 'status')->pluck('to_status')->all();
    expect($statussen)->toBe(['creator_zoeken', 'creator_gevonden', 'bezoek_gepland', 'bezoek_geweest', 'content_maken', 'ter_controle', 'feedback', 'ter_controle', 'goedgekeurd', 'live', 'afgerond']);
});

test('Sophie (Elite): vergoeding van 160 euro opeisbaar bij Live in de betaalrun van vrijdag', function () {
    $c = $this->dienst->aanvragen($this->bloesem, $this->sanne, [
        'location_id' => $this->bloesem->locations()->first()->id, 'title' => 'Brunch', 'briefing' => 'Brunch op zondag.', 'creator_level' => 'elite',
    ]);
    $c = doorloopTotLive($this->dienst, $c, $this->sophie, $this->eva, $this->ruben);

    $payout = Payout::sole();
    expect($payout->amount_cents)->toBe(16000)
        ->and($payout->run_on->isFriday())->toBeTrue()
        ->and($this->bloesem->availablePoints())->toBe(12);
});

test('reclamevermelding ontbreekt: duidelijke uitleg en geen nieuwe status', function () {
    $c = $this->dienst->aanvragen($this->bloesem, $this->sanne, [
        'location_id' => $this->bloesem->locations()->first()->id, 'title' => 'X', 'briefing' => 'X', 'creator_level' => 'bronze',
    ]);
    $this->dienst->uitnodigen($c, $this->marit, $this->eva);
    $this->dienst->accepteren($c->fresh()->invitations()->first(), $this->marit->user, 'Marit', '127.0.0.1');
    $moment = CarbonImmutable::tomorrow()->setTime(12, 0);
    $this->dienst->tijdvakKiezen($c->fresh(), $this->marit->user, $moment);
    $this->travelTo($moment);
    $this->dienst->inchecken($c->fresh(), $this->marit->user);
    $this->dienst->klaarMetFilmen($c->fresh(), $this->marit->user);

    expect(fn () => $this->dienst->indienen($c->fresh(), $this->marit->user, UploadedFile::fake()->create('v.mp4', 10), 'Lekker gegeten #adv', ['label_platform' => true, 'label_beeld' => true]))
        ->toThrow(ValidationException::class);
    expect($c->fresh()->status)->toBe(Status::ContentMaken);
});

test('annuleren in status 1 of 2 geeft alle punten terug', function () {
    $c = $this->dienst->aanvragen($this->bloesem, $this->sanne, [
        'location_id' => $this->bloesem->locations()->first()->id, 'title' => 'X', 'briefing' => 'X', 'creator_level' => 'gold',
    ]);
    expect($this->bloesem->availablePoints())->toBe(15);
    $this->dienst->annuleren($c, $this->sanne, 'Toch niet', false);
    expect($this->bloesem->availablePoints())->toBe(20)->and($c->fresh()->status)->toBe(Status::Geannuleerd);
});

test('alcohol in beeld: alleen creators van 25 jaar of ouder', function () {
    $c = $this->dienst->aanvragen($this->bloesem, $this->sanne, [
        'location_id' => $this->bloesem->locations()->first()->id, 'title' => 'Borrel', 'briefing' => 'X', 'creator_level' => 'bronze', 'alcohol' => true,
    ]);
    expect(fn () => $this->dienst->uitnodigen($c, $this->marit, $this->eva))->toThrow(ValidationException::class);
});

test('te weinig tegoed: aanvraag wordt geweigerd', function () {
    $this->bloesem->ledger()->create(['kind' => 'correctie', 'points' => -18, 'reason' => 'test']);
    expect(fn () => $this->dienst->aanvragen($this->bloesem, $this->sanne, [
        'location_id' => $this->bloesem->locations()->first()->id, 'title' => 'X', 'briefing' => 'X', 'creator_level' => 'bronze',
    ]))->toThrow(ValidationException::class);
});
