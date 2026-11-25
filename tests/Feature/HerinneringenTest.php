<?php

use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Creator;
use App\Models\Lead;
use App\Models\MessageTemplate;
use App\Models\Status;
use App\Models\User;
use App\Notifications\PlatformMelding;
use App\Services\Herinneringen;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->startOfWeek()->setTime(10, 0)); // maandag 10:00, voor voorspelbare werkdagen
    Notification::fake();
    $this->seed(DemoSeeder::class);
    $this->h = app(Herinneringen::class);
    $this->bloesem = Company::where('name', 'Lunchroom Bloesem')->first();
    $this->sanne = User::where('email', 'sanne@demo.mebranding.nl')->first();
    $this->eva = User::where('email', 'eva@demo.mebranding.nl')->first();
    $this->ruben = User::where('email', 'ruben@demo.mebranding.nl')->first();
    $this->marit = Creator::whereHas('user', fn ($q) => $q->where('email', 'marit@demo.mebranding.nl'))->first();
});

function samenwerking(Company $bedrijf, ?Creator $creator, Status $status, array $velden = []): Collaboration
{
    return Collaboration::forceCreate([
        'number' => 'S'.fake()->unique()->numerify('######'), 'company_id' => $bedrijf->id, 'location_id' => $bedrijf->locations()->first()->id,
        'creator_id' => $creator?->id, 'title' => 'Kerstlunch', 'briefing' => 'Laat de kerstlunch zien.', 'status' => $status,
        'points' => 3, 'creator_level' => 'bronze',
    ] + $velden);
}

/** Verstuurd met dit sjabloon, en elke placeholder in onderwerp, push, in-app en mail heeft een waarde. */
function verstuurd(User $aan, string $sleutel, ?callable $extra = null): void
{
    Notification::assertSentTo($aan, PlatformMelding::class, function (PlatformMelding $n) use ($sleutel, $extra) {
        if ($n->template->key !== $sleutel) {
            return false;
        }
        $teksten = [$n->template->subject, $n->template->push, $n->template->inapp, ...($n->template->email['alineas'] ?? [])];
        foreach ($teksten as $t) {
            expect(MessageTemplate::vulIn($t, $n->vars))->not->toMatch('/\{\w+\}/');
        }

        return $extra ? $extra($n) : true;
    });
}

function aantalVerstuurd(string $sleutel): int
{
    return User::all()->sum(fn ($u) => Notification::sent($u, PlatformMelding::class, fn ($n) => $n->template->key === $sleutel)->count());
}

test('bezoek morgen: ondernemer en creator krijgen een herinnering, één keer', function () {
    $c = samenwerking($this->bloesem, $this->marit, Status::BezoekGepland, ['visit_at' => today()->addDay()->setTime(14, 30)]);
    samenwerking($this->bloesem, $this->marit, Status::BezoekGepland, ['visit_at' => today()->addDays(2)->setTime(14, 30)]);

    expect($this->h->bezoekMorgen())->toBe(1);
    verstuurd($this->sanne, 'o-bezoek-morgen', fn ($n) => $n->vars['tijd'] === '14:30' && str_contains($n->url, "/ondernemer/samenwerkingen/{$c->id}"));
    verstuurd($this->marit->user, 'c-bezoek-morgen', fn ($n) => $n->vars['zaak'] === 'Lunchroom Bloesem');

    expect($this->h->bezoekMorgen())->toBe(0)
        ->and(aantalVerstuurd('c-bezoek-morgen'))->toBe(1)
        ->and($c->fresh()->herinneringen)->toHaveKey('bezoek-morgen');
});

test('herinnering reageren: halverwege de reactietermijn, met het aantal uren dat nog rest', function () {
    $c = samenwerking($this->bloesem, null, Status::CreatorZoeken);
    $u = $c->invitations()->create(['creator_id' => $this->marit->id, 'invited_by' => $this->eva->id, 'expires_at' => now()->addHours(48)]);

    $this->travel(23)->hours();
    expect($this->h->reageer())->toBe(0);

    $this->travel(2)->hours();
    expect($this->h->reageer())->toBe(1);
    verstuurd($this->marit->user, 'c-herinnering-reageer', fn ($n) => $n->vars['aantal'] === '23');
    expect($this->h->reageer())->toBe(0);

    // Beantwoord: geen herinnering meer
    $u->update(['answer' => 'geweigerd', 'answered_at' => now()]);
    expect($this->h->reageer())->toBe(0);
});

test('deadline over 48 uur, daarna te laat voor creator en accountmanager, maar niet als er al een versie is', function () {
    $c = samenwerking($this->bloesem, $this->marit, Status::ContentMaken, ['content_deadline_at' => now()->addDays(3)]);
    $metVersie = samenwerking($this->bloesem, $this->marit, Status::ContentMaken, ['content_deadline_at' => now()->addDay()]);
    $metVersie->versions()->create(['version' => 1, 'disk' => 'local', 'path' => 'x.mp4', 'mime' => 'video/mp4', 'size' => 1, 'caption' => 'Advertentie', 'label_check' => ['geslaagd' => true], 'submitted_by' => $this->marit->user_id]);

    expect($this->h->deadline())->toBe(0);

    $this->travel(25)->hours();
    expect($this->h->deadline())->toBe(1)->and($this->h->teLaat())->toBe(0);
    verstuurd($this->marit->user, 'c-deadline', fn ($n) => $n->vars['datum'] === $c->content_deadline_at->translatedFormat('l j F'));

    $this->travel(2)->days();
    expect($this->h->teLaat())->toBe(1)->and($this->h->teLaat())->toBe(0);
    verstuurd($this->marit->user, 'c-te-laat');
    verstuurd($this->eva, 't-content-laat', fn ($n) => $n->vars['creator'] === $this->marit->user->name && str_contains($n->url, "/team/samenwerkingen/{$c->id}"));
    expect(aantalVerstuurd('t-content-laat'))->toBe(1);
});

test('publicatiedag en twee dagen later zonder link een herinnering; met link niets', function () {
    $c = samenwerking($this->bloesem, $this->marit, Status::Goedgekeurd, ['publish_on' => today()]);

    expect($this->h->publiceerVandaag())->toBe(1)->and($this->h->publiceerVandaag())->toBe(0)->and($this->h->post())->toBe(0);
    verstuurd($this->marit->user, 'c-publiceer-vandaag');

    $this->travel(2)->days();
    expect($this->h->post())->toBe(1)->and($this->h->post())->toBe(0);
    verstuurd($this->marit->user, 'c-herinnering-post', fn ($n) => $n->vars['datum'] === $c->publish_on->translatedFormat('l j F'));

    $live = samenwerking($this->bloesem, $this->marit, Status::Live, ['publish_on' => today()->subDays(2)]);
    expect($this->h->post())->toBe(0)->and($live->fresh()->herinneringen)->toBeNull();
});

test('teamsignalen: te lang creator zoeken en te lang ter controle', function () {
    $c = samenwerking($this->bloesem, null, Status::CreatorZoeken);
    expect($this->h->zoekenLang())->toBe(0);

    $this->travel(3)->days(); // donderdag: drie werkdagen
    expect($this->h->zoekenLang())->toBe(1)->and($this->h->zoekenLang())->toBe(0);
    verstuurd($this->eva, 't-zoeken-lang', fn ($n) => $n->vars['dagen'] === '3' && $n->vars['samenwerking'] === 'Kerstlunch');

    $controle = samenwerking($this->bloesem, $this->marit, Status::TerControle);
    $controle->versions()->create(['version' => 1, 'disk' => 'local', 'path' => 'x.mp4', 'mime' => 'video/mp4', 'size' => 1, 'caption' => 'Advertentie', 'label_check' => ['geslaagd' => true], 'submitted_by' => $this->marit->user_id]);
    expect($this->h->controleLang())->toBe(0);

    $this->travel(1)->days();
    expect($this->h->controleLang())->toBe(1)->and($this->h->controleLang())->toBe(0);
    verstuurd($this->ruben, 't-controle-lang', fn ($n) => $n->vars['zaak'] === 'Lunchroom Bloesem');
});

test('nieuwe aanmelding als creator: het team met recht creators.screenen krijgt t-aanmelding', function () {
    $lead = Lead::create([
        'type' => Lead::TYPE_CREATOR, 'name' => 'Jet de Boer', 'email' => 'jet@example.com', 'city' => 'Sneek',
        'data' => ['niches' => ['reizen']], 'consent_at' => now(), 'consent_version' => '0.1-concept',
    ]);

    verstuurd($this->ruben, 't-aanmelding', fn ($n) => $n->vars['naam'] === 'Jet de Boer' && $n->vars['plaats'] === 'Sneek'
        && $n->vars['soort'] === 'creator (Reizen en hotels)' && str_contains($n->url, "/team/screening/{$lead->id}"));
    Notification::assertNotSentTo($this->sanne, PlatformMelding::class);
});
