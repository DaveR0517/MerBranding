<?php

use App\Jobs\ZetStudioVideoOm;
use App\Models\StudioDesign;
use App\Models\StudioExport;
use App\Models\StudioMedia;
use App\Models\StudioTemplate;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\StudioSjablonenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

const STUDIO_APP = 'http://app.mebranding.test';

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->seed(StudioSjablonenSeeder::class);
    Storage::fake('local');
    $this->wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
    $this->eva = ($this->wie)('eva');
    $this->paginas = [['achtergrond' => '#FFFFFF', 'lagen' => [
        ['id' => 't1', 'soort' => 'tekst', 'naam' => 'Titel', 'x' => 10, 'y' => 20, 'breedte' => 500, 'hoogte' => 80, 'rotatie' => 0, 'zichtbaar' => true, 'vergrendeld' => false, 'tekst' => 'Hallo Friesland'],
    ]]];
});

test('alleen team met studio.gebruiken komt in MeStudio; Super Admin altijd', function () {
    $this->actingAs(($this->wie)('sanne'))->get(STUDIO_APP.'/team/studio/ontwerpen')->assertForbidden();
    $this->actingAs(($this->wie)('marit'))->get(STUDIO_APP.'/team/studio/ontwerpen')->assertForbidden();

    $zonderRecht = User::factory()->create();
    $zonderRecht->assignRole('Medewerker');
    $this->actingAs($zonderRecht)->get(STUDIO_APP.'/team/studio/ontwerpen')->assertForbidden();
    $this->actingAs($zonderRecht)->get(STUDIO_APP.'/team/studio/feedplanner')->assertForbidden();

    $this->actingAs($this->eva)->get(STUDIO_APP.'/team/studio/ontwerpen')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('team/studio/ontwerpen')->has('sjablonen', 8));

    $baas = User::factory()->create();
    $baas->assignRole('Super Admin');
    foreach (['ontwerpen', 'sjablonen', 'feedplanner', 'mediatheek'] as $scherm) {
        $this->actingAs($baas)->get(STUDIO_APP."/team/studio/{$scherm}")->assertOk();
    }
});

test('de huisstijlsjablonen zijn idempotent en hebben vakken', function () {
    $this->seed(StudioSjablonenSeeder::class);
    expect(StudioTemplate::count())->toBe(8);

    $video = StudioTemplate::where('slug', 'video-teaser')->sole();
    expect($video->width)->toBe(1080)->and($video->height)->toBe(1920)
        ->and(collect($video->pages[0]['lagen'])->where('soort', 'video')->where('vak', true))->toHaveCount(1);
    expect(count(StudioTemplate::where('slug', 'carrousel-zo-werkt-het')->sole()->pages))->toBe(3);

    // Aanpassing van het team blijft staan bij opnieuw seeden.
    $video->update(['name' => 'Eigen naam']);
    $this->seed(StudioSjablonenSeeder::class);
    expect($video->fresh()->name)->toBe('Eigen naam');
});

test('nieuw ontwerp in een formaat, opslaan als geheel en weer laden', function () {
    $this->actingAs($this->eva)->post(STUDIO_APP.'/team/studio/ontwerpen', ['formaat' => 'story'])->assertRedirect();
    $ontwerp = StudioDesign::sole();
    expect($ontwerp->width)->toBe(1080)->and($ontwerp->height)->toBe(1920)->and($ontwerp->status)->toBe('concept');

    $jpg = 'data:image/jpeg;base64,'.base64_encode(UploadedFile::fake()->image('v.jpg', 60, 100)->getContent());
    $this->actingAs($this->eva)->putJson(STUDIO_APP."/team/studio/ontwerpen/{$ontwerp->id}", ['name' => 'Story december', 'pages' => $this->paginas, 'voorvertoning' => $jpg])
        ->assertOk()->assertJson(['ok' => true, 'heeft_video' => false]);

    $ontwerp->refresh();
    expect($ontwerp->name)->toBe('Story december')->and($ontwerp->pages[0]['lagen'][0]['tekst'])->toBe('Hallo Friesland');
    Storage::disk('local')->assertExists($ontwerp->preview_path);

    $this->actingAs($this->eva)->get(STUDIO_APP."/team/studio/ontwerpen/{$ontwerp->id}")->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('team/studio/editor')->where('item.pages.0.lagen.0.tekst', 'Hallo Friesland')->has('logos'));
    $this->actingAs($this->eva)->get(STUDIO_APP."/team/studio/ontwerpen/{$ontwerp->id}/voorvertoning")->assertOk();

    // Geen tweede pagina buiten een carrousel; onbekende laagsoort geweigerd.
    $this->actingAs($this->eva)->putJson(STUDIO_APP."/team/studio/ontwerpen/{$ontwerp->id}", ['name' => 'x', 'pages' => [...$this->paginas, ...$this->paginas]])->assertStatus(422);
    $this->actingAs($this->eva)->putJson(STUDIO_APP."/team/studio/ontwerpen/{$ontwerp->id}", ['name' => 'x', 'pages' => [['lagen' => [['id' => 'a', 'soort' => 'script']]]]])->assertStatus(422);
});

test('sjabloon gebruiken maakt een kopie; het sjabloon blijft onaangeroerd', function () {
    $sjabloon = StudioTemplate::where('slug', 'creator-in-de-spotlight')->sole();
    $origineel = $sjabloon->pages;

    $this->actingAs($this->eva)->post(STUDIO_APP."/team/studio/sjablonen/{$sjabloon->id}/gebruiken")->assertRedirect();
    $ontwerp = StudioDesign::sole();
    expect($ontwerp->template_id)->toBe($sjabloon->id)->and($ontwerp->pages)->toBe($origineel)
        ->and($ontwerp->width)->toBe(1080)->and($ontwerp->height)->toBe(1350);

    $this->actingAs($this->eva)->putJson(STUDIO_APP."/team/studio/ontwerpen/{$ontwerp->id}", ['name' => 'Gewijzigd', 'pages' => $this->paginas])->assertOk();
    expect($sjabloon->fresh()->pages)->toBe($origineel);

    // Opslaan als sjabloon, dupliceren, hernoemen, archiveren (niet meer te gebruiken) en terugzetten.
    $this->actingAs($this->eva)->post(STUDIO_APP."/team/studio/ontwerpen/{$ontwerp->id}/sjabloon", ['name' => 'Mijn sjabloon'])->assertRedirect();
    $eigen = StudioTemplate::where('name', 'Mijn sjabloon')->sole();
    expect($eigen->is_builtin)->toBeFalse()->and($eigen->pages)->toBe($this->paginas);

    $this->actingAs($this->eva)->post(STUDIO_APP."/team/studio/sjablonen/{$eigen->id}/dupliceren");
    expect(StudioTemplate::where('name', 'Mijn sjabloon (kopie)')->exists())->toBeTrue();
    $this->actingAs($this->eva)->patch(STUDIO_APP."/team/studio/sjablonen/{$eigen->id}/naam", ['name' => 'Nieuwe naam']);
    expect($eigen->fresh()->name)->toBe('Nieuwe naam');
    $this->actingAs($this->eva)->post(STUDIO_APP."/team/studio/sjablonen/{$eigen->id}/archiveren");
    expect($eigen->fresh()->archived_at)->not->toBeNull();
    $this->actingAs($this->eva)->post(STUDIO_APP."/team/studio/sjablonen/{$eigen->id}/gebruiken")->assertStatus(422);
    $this->actingAs($this->eva)->post(STUDIO_APP."/team/studio/sjablonen/{$eigen->id}/archiveren");
    expect($eigen->fresh()->archived_at)->toBeNull();
});

test('sjabloon vanaf een eigen achtergrond: vergrendelde achtergrondlaag', function () {
    $this->actingAs($this->eva)->post(STUDIO_APP.'/team/studio/sjablonen/achtergrond', [
        'formaat' => 'instagram_post', 'bestand' => UploadedFile::fake()->image('kit.png', 1080, 1080),
    ])->assertRedirect();

    $sjabloon = StudioTemplate::latest('id')->first();
    $laag = $sjabloon->pages[0]['lagen'][0];
    expect($laag['vergrendeld'])->toBeTrue()->and($laag['bron']['soort'])->toBe('media')
        ->and(StudioMedia::find($laag['bron']['id'])->width)->toBe(1080);
});

test('mediatheek: upload privé, beveiligd bekijken, grens per bestand', function () {
    $this->actingAs($this->eva)->postJson(STUDIO_APP.'/team/studio/mediatheek', ['bestand' => UploadedFile::fake()->image('team.jpg', 800, 600)])
        ->assertOk()->assertJsonPath('media.kind', 'foto')->assertJsonPath('media.width', 800);
    $media = StudioMedia::sole();
    Storage::disk('local')->assertExists($media->path);
    expect($media->path)->toStartWith('studio/media/');

    $this->actingAs($this->eva)->get(STUDIO_APP."/team/studio/mediatheek/{$media->id}/bestand")->assertOk();
    $this->actingAs(($this->wie)('sanne'))->get(STUDIO_APP."/team/studio/mediatheek/{$media->id}/bestand")->assertForbidden();

    config(['studio.max_upload_mb' => 1]);
    $this->actingAs($this->eva)->postJson(STUDIO_APP.'/team/studio/mediatheek', ['bestand' => UploadedFile::fake()->create('groot.mp4', 2048, 'video/mp4')])
        ->assertStatus(422)->assertJsonValidationErrors('bestand');
});

test('export uploaden en alleen via de beveiligde route downloaden; carrousel als zip', function () {
    $this->actingAs($this->eva)->post(STUDIO_APP.'/team/studio/ontwerpen', ['formaat' => 'carrousel']);
    $ontwerp = StudioDesign::sole();
    expect(count($ontwerp->pages))->toBe(3);

    foreach ([1, 2, 3] as $pagina) {
        $this->actingAs($this->eva)->postJson(STUDIO_APP."/team/studio/ontwerpen/{$ontwerp->id}/exports", [
            'soort' => 'png', 'pagina' => $pagina, 'bestand' => UploadedFile::fake()->image("p{$pagina}.png", 1080, 1350),
        ])->assertOk()->assertJsonPath('export.status', 'klaar');
    }
    $export = StudioExport::first();
    expect($export->path)->toStartWith("studio/exports/{$ontwerp->id}/");
    [$breedte] = getimagesize(Storage::disk('local')->path($export->path));
    expect($breedte)->toBe(1080);

    $this->actingAs($this->eva)->get(STUDIO_APP."/team/studio/exports/{$export->id}")->assertOk()->assertDownload();
    $this->actingAs(($this->wie)('sanne'))->get(STUDIO_APP."/team/studio/exports/{$export->id}")->assertForbidden();
    $this->actingAs(($this->wie)('marit'))->get(STUDIO_APP."/team/studio/exports/{$export->id}")->assertForbidden();

    $zip = $this->actingAs($this->eva)->get(STUDIO_APP."/team/studio/ontwerpen/{$ontwerp->id}/zip")->assertOk()->assertDownload();
    $archief = new ZipArchive;
    $archief->open($zip->baseResponse->getFile()->getPathname());
    expect($archief->numFiles)->toBe(3);

    // Een afbeelding als video aanbieden mag niet.
    $this->actingAs($this->eva)->postJson(STUDIO_APP."/team/studio/ontwerpen/{$ontwerp->id}/exports", ['soort' => 'webm', 'bestand' => UploadedFile::fake()->image('x.png')])->assertStatus(422);
});

test('videoverslag: webm gaat op de queue en wordt met ffmpeg een MP4', function () {
    $this->actingAs($this->eva)->post(STUDIO_APP.'/team/studio/ontwerpen', ['formaat' => 'story']);
    $ontwerp = StudioDesign::sole();

    Queue::fake();
    $this->actingAs($this->eva)->postJson(STUDIO_APP."/team/studio/ontwerpen/{$ontwerp->id}/exports", [
        'soort' => 'webm', 'bestand' => UploadedFile::fake()->create('opname.webm', 50, 'video/webm'),
    ])->assertOk()->assertJsonPath('export.status', 'omzetten');
    Queue::assertPushed(ZetStudioVideoOm::class);
    $webm = StudioExport::sole();

    // Nep-ffmpeg: schrijft het laatste argument (het doelbestand), zodat de test niet van de server afhangt.
    $nep = sys_get_temp_dir().'/nep-ffmpeg-'.uniqid();
    file_put_contents($nep, "#!/bin/sh\nfor last; do true; done\necho mp4 > \"\$last\"\n");
    chmod($nep, 0755);
    config(['studio.ffmpeg' => $nep]);
    (new ZetStudioVideoOm($webm))->handle();

    expect($webm->fresh()->status)->toBe('klaar');
    $mp4 = StudioExport::where('kind', 'mp4')->sole();
    expect($mp4->mime)->toBe('video/mp4')->and($mp4->path)->toEndWith('.mp4');
    Storage::disk('local')->assertExists($mp4->path);
    unlink($nep);

    // Zonder ffmpeg: de webm blijft, met een eerlijke melding.
    config(['studio.ffmpeg' => '/bestaat/niet/ffmpeg']);
    $tweede = StudioExport::create(['design_id' => $ontwerp->id, 'kind' => 'webm', 'path' => 'studio/exports/x.webm', 'status' => 'omzetten']);
    (new ZetStudioVideoOm($tweede))->handle();
    expect($tweede->fresh())->status->toBe('alleen_webm')->message->toContain('ffmpeg ontbreekt');
});

test('echte ffmpeg zet een webm om naar H.264/AAC', function () {
    $ffmpeg = ZetStudioVideoOm::ffmpeg();
    if (! $ffmpeg) {
        $this->markTestSkipped('ffmpeg is niet aanwezig.');
    }
    $this->actingAs($this->eva)->post(STUDIO_APP.'/team/studio/ontwerpen', ['formaat' => 'story']);
    $ontwerp = StudioDesign::sole();
    $pad = Storage::disk('local')->path('studio/exports/test.webm');
    @mkdir(dirname($pad), 0777, true);
    exec(escapeshellarg($ffmpeg).' -y -loglevel error -f lavfi -i color=c=red:s=108x192:d=1 -f lavfi -i sine=d=1 -c:v libvpx -c:a libopus -shortest '.escapeshellarg($pad));
    $webm = StudioExport::create(['design_id' => $ontwerp->id, 'kind' => 'webm', 'path' => 'studio/exports/test.webm', 'status' => 'omzetten']);

    (new ZetStudioVideoOm($webm))->handle();

    $mp4 = StudioExport::where('kind', 'mp4')->sole();
    $info = shell_exec('ffprobe -v error -show_entries stream=codec_name -of csv=p=0 '.escapeshellarg(Storage::disk('local')->path($mp4->path)));
    expect($info)->toContain('h264')->toContain('aac');
});

test('feedplanner: volgorde slepen, inplannen en markeren als geplaatst', function () {
    $maak = fn (string $naam, string $status) => StudioDesign::create(['name' => $naam, 'format' => 'instagram_post', 'width' => 1080, 'height' => 1080, 'pages' => $this->paginas, 'status' => $status]);
    $a = $maak('A', 'klaar');
    $b = $maak('B', 'klaar');
    $c = $maak('C', 'concept');

    $this->actingAs($this->eva)->post(STUDIO_APP.'/team/studio/feedplanner/volgorde', ['ids' => [$a->id, $b->id]])->assertRedirect();
    expect($a->fresh()->feed_position)->toBeGreaterThan($b->fresh()->feed_position);

    $this->actingAs($this->eva)->get(STUDIO_APP.'/team/studio/feedplanner')->assertInertia(fn (AssertableInertia $p) => $p
        ->component('team/studio/feedplanner')->where('feed.0.name', 'A')->where('feed.1.name', 'B')->where('concepten.0.name', 'C'));

    $this->actingAs($this->eva)->post(STUDIO_APP.'/team/studio/feedplanner/volgorde', ['ids' => [$b->id, $a->id]]);
    $this->actingAs($this->eva)->get(STUDIO_APP.'/team/studio/feedplanner')->assertInertia(fn (AssertableInertia $p) => $p->where('feed.0.name', 'B'));

    // Inplannen vraagt een datum; een concept dat in de feed komt, staat bovenaan.
    $this->actingAs($this->eva)->patch(STUDIO_APP."/team/studio/feedplanner/{$c->id}", ['status' => 'ingepland'])->assertSessionHasErrors('scheduled_at');
    $this->actingAs($this->eva)->patch(STUDIO_APP."/team/studio/feedplanner/{$c->id}", ['status' => 'ingepland', 'scheduled_at' => '2026-12-01 12:00'])->assertSessionHasNoErrors();
    expect($c->fresh()->feed_position)->toBeGreaterThan($b->fresh()->feed_position);

    // Bijschrift: hooguit 2200 tekens en 30 hashtags.
    $this->actingAs($this->eva)->patch(STUDIO_APP."/team/studio/feedplanner/{$c->id}", ['caption' => str_repeat('a', 2201)])->assertSessionHasErrors('caption');
    $this->actingAs($this->eva)->patch(STUDIO_APP."/team/studio/feedplanner/{$c->id}", ['caption' => implode(' ', array_map(fn ($i) => "#tag{$i}", range(1, 31)))])->assertSessionHasErrors('caption');

    $this->actingAs($this->eva)->patch(STUDIO_APP."/team/studio/feedplanner/{$c->id}", ['status' => 'geplaatst', 'post_url' => 'https://www.instagram.com/p/abc/'])->assertSessionHasNoErrors();
    expect($c->fresh())->status->toBe('geplaatst')->post_url->toBe('https://www.instagram.com/p/abc/')->posted_at->not->toBeNull();
});
