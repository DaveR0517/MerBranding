<?php

use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Creator;
use App\Models\Publication;
use App\Models\StatSnapshot;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PlatformMelding;
use App\Services\Cijfers;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->seed(DemoSeeder::class);
    $this->app_url = 'http://app.mebranding.test';
    $this->wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
    $this->bloesem = Company::where('name', 'Lunchroom Bloesem')->first();

    // Een samenwerking die Live staat, in de meetperiode, met één publicatie.
    $this->live = function (Company $bedrijf, Creator $creator, string $nummer): Publication {
        $c = Collaboration::create([
            'number' => $nummer, 'company_id' => $bedrijf->id, 'location_id' => $bedrijf->locations()->value('id'), 'creator_id' => $creator->id,
            'title' => "Post {$nummer}", 'briefing' => 'Test', 'status' => Status::Live, 'points' => 3, 'creator_level' => $creator->level,
            'live_at' => now(), 'measure_until' => now()->addDays(14),
        ]);

        return $c->publications()->create(['platform' => 'instagram', 'url' => "https://www.instagram.com/p/{$nummer}/", 'posted_at' => now()]);
    };
});

test('de creator voert cijfers in met verplichte screenshot, alleen tijdens de meetperiode en alleen voor de eigen klus', function () {
    $marit = ($this->wie)('marit');
    $p = ($this->live)($this->bloesem, $marit->creator, 'S-1');
    $url = "{$this->app_url}/creator/cijfers/{$p->collaboration_id}";

    $this->actingAs($marit)->get($url)->assertOk();

    // Zonder screenshot: geweigerd
    $this->actingAs($marit)->post($url, ['publication_id' => $p->id, 'views' => 1200])->assertSessionHasErrors('screenshot');
    expect(StatSnapshot::count())->toBe(0);

    // Met screenshot: twee ruwe metingen, bron handmatig, nog niet gecontroleerd
    $this->actingAs($marit)->post($url, [
        'publication_id' => $p->id, 'views' => 1200, 'likes' => 80, 'screenshot' => UploadedFile::fake()->image('cijfers.png'),
    ])->assertSessionHasNoErrors();
    expect(StatSnapshot::count())->toBe(2)
        ->and(StatSnapshot::first()->source)->toBe('handmatig')
        ->and(StatSnapshot::first()->reviewed_at)->toBeNull();
    Storage::disk('local')->assertExists(StatSnapshot::first()->screenshot_path);

    // Taak voor het team, melding t-cijfers-handmatig naar wie samenwerkingen mag bekijken, regel in het logboek
    expect(Task::open()->where('type', Cijfers::TAAK)->count())->toBe(1)
        ->and(ActivityLog::where('event', 'cijfers_ingevoerd')->count())->toBe(1);
    Notification::assertSentTo(($this->wie)('eva'), PlatformMelding::class, fn ($n) => $n->template->key === 't-cijfers-handmatig');
    Notification::assertNotSentTo(($this->wie)('mirthe'), PlatformMelding::class);

    // Een andere creator mag hier niets
    $this->actingAs(($this->wie)('sophie'))->get($url)->assertForbidden();
    $this->actingAs(($this->wie)('sophie'))->post($url, ['publication_id' => $p->id, 'views' => 5, 'screenshot' => UploadedFile::fake()->image('x.png')])->assertForbidden();

    // Na de meetperiode: niet meer
    $this->travel(15)->days();
    $this->actingAs($marit)->post($url, ['publication_id' => $p->id, 'views' => 1500, 'screenshot' => UploadedFile::fake()->image('c.png')])->assertSessionHasErrors('views');
});

test('het team corrigeert met reden en logregel; de oude meting blijft bewaard; akkoord sluit de taak', function () {
    $marit = ($this->wie)('marit');
    $ruben = ($this->wie)('ruben');
    $p = ($this->live)($this->bloesem, $marit->creator, 'S-2');
    app(Cijfers::class)->invoeren($p, $marit, ['views' => 1200, 'likes' => 80], UploadedFile::fake()->image('c.png'), false);
    $views = StatSnapshot::where('metric', 'views')->first();

    // Zonder het recht: geweigerd (Sales heeft geen samenwerkingen.bekijken)
    $this->actingAs(($this->wie)('mirthe'))->post("{$this->app_url}/team/cijfers/metingen/{$views->id}/corrigeren", ['value' => 1, 'reason' => 'x'])->assertForbidden();

    $this->actingAs($ruben)->post("{$this->app_url}/team/cijfers/metingen/{$views->id}/corrigeren", ['value' => 1250])->assertSessionHasErrors('reason');
    $this->actingAs($ruben)->post("{$this->app_url}/team/cijfers/metingen/{$views->id}/corrigeren", ['value' => 1250, 'reason' => 'Screenshot toont 1.250'])->assertSessionHasNoErrors();

    expect(StatSnapshot::count())->toBe(3)
        ->and($views->fresh()->value)->toBe(1200);
    $laatste = app(Cijfers::class)->perPublicatie(collect([$p]))[0];
    expect($laatste['metrics']['views']['value'])->toBe(1250)
        ->and($laatste['unreviewed'])->toBeTrue(); // likes nog niet gecontroleerd
    $log = ActivityLog::where('event', 'cijfers_gecorrigeerd')->sole();
    expect($log->user_id)->toBe($ruben->id)->and($log->data)->toMatchArray(['van' => 1200, 'naar' => 1250]);
    expect(Task::open()->where('type', Cijfers::TAAK)->count())->toBe(1);

    $this->actingAs($ruben)->post("{$this->app_url}/team/cijfers/samenwerkingen/{$p->collaboration_id}/akkoord")->assertSessionHasNoErrors();
    expect(Task::open()->where('type', Cijfers::TAAK)->count())->toBe(0)
        ->and(app(Cijfers::class)->perPublicatie(collect([$p]))[0]['unreviewed'])->toBeFalse();

    // Team mag zelf invoeren zonder screenshot, met reden
    $this->actingAs($ruben)->post("{$this->app_url}/team/cijfers/publicaties/{$p->id}", ['shares' => 12, 'reason' => 'Per mail ontvangen'])->assertSessionHasNoErrors();
    expect(StatSnapshot::where('metric', 'shares')->sole()->reviewed_at)->not->toBeNull();

    $this->actingAs($ruben)->get("{$this->app_url}/team/cijfers")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('team/cijfers/index')->has('collaborations', 1));
    // Screenshot: team ja, ondernemer niet
    $this->actingAs($ruben)->get("{$this->app_url}/team/cijfers/metingen/{$views->id}/screenshot")->assertOk();
    $this->actingAs(($this->wie)('sanne'))->get("{$this->app_url}/team/cijfers/metingen/{$views->id}/screenshot")->assertForbidden();
});

test('de ondernemer ziet alleen cijfers en video\'s van zijn eigen samenwerkingen, per platform', function () {
    $sanne = ($this->wie)('sanne');
    $marit = ($this->wie)('marit');

    $ander = Company::create(['name' => 'Bakkerij Anders']);
    $ander->locations()->create(['name' => 'Dokkum', 'city' => 'Dokkum']);
    $piet = User::factory()->create(['email' => 'piet@demo.mebranding.nl']);
    $piet->assignRole('Ondernemer Eigenaar');
    $ander->users()->attach($piet->id, ['role' => 'eigenaar']);

    $eigen = ($this->live)($this->bloesem, $marit->creator, 'S-3');
    $vreemd = ($this->live)($ander, ($this->wie)('sophie')->creator, 'S-4');
    $tiktok = Publication::create(['collaboration_id' => $eigen->collaboration_id, 'platform' => 'tiktok', 'url' => 'https://tiktok.com/x', 'posted_at' => now()]);
    foreach ([[$eigen, 'instagram', 1000], [$tiktok, 'tiktok', 4000], [$vreemd, 'instagram', 99999]] as [$p, $bron, $views]) {
        StatSnapshot::create(['publication_id' => $p->id, 'metric' => 'views', 'value' => $views, 'source' => $bron, 'fetched_at' => now(), 'is_estimate' => $bron === 'tiktok']);
    }

    $this->actingAs($sanne)->get("{$this->app_url}/ondernemer/resultaten")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('ondernemer/resultaten/index')
            ->has('collaborations', 1)
            ->where('collaborations.0.id', $eigen->collaboration_id)
            ->has('platforms', 2) // niet opgeteld over platforms heen
            ->where('platforms.0.platform', 'instagram')->where('platforms.0.totals.views', 1000)
            ->where('platforms.1.platform', 'tiktok')->where('platforms.1.totals.views', 4000)->where('platforms.1.is_estimate', true));

    $this->actingAs($piet)->get("{$this->app_url}/ondernemer/resultaten")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('platforms.0.totals.views', 99999)->has('collaborations', 1));

    // Mediabibliotheek: alleen goedgekeurde video's van het eigen bedrijf
    foreach ([[$eigen, 'goedgekeurd'], [$eigen, 'feedback'], [$vreemd, 'goedgekeurd']] as $i => [$p, $uitkomst]) {
        Collaboration::find($p->collaboration_id)->versions()->create([
            'version' => $i + 1, 'disk' => 'local', 'path' => "v{$i}.mp4", 'caption' => 'Advertentie', 'label_check' => [], 'outcome' => $uitkomst,
        ]);
    }
    $this->actingAs($sanne)->get("{$this->app_url}/ondernemer/mediabibliotheek")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('ondernemer/resultaten/media')->has('videos', 1));

    // Maandrapport van het eigen bedrijf, ook afdrukbaar
    $maand = now()->format('Y-m');
    $this->actingAs($sanne)->get("{$this->app_url}/ondernemer/maandrapport/{$maand}")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('rapport.bedrijf.name', 'Lunchroom Bloesem')->has('rapport.publicaties', 2));
    $this->actingAs($sanne)->get("{$this->app_url}/ondernemer/maandrapport/{$maand}/afdrukken")->assertOk()->assertSee('Lunchroom Bloesem')->assertDontSee('Bakkerij Anders');
});
