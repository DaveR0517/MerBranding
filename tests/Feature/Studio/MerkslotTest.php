<?php

use App\Models\ActivityLog;
use App\Models\StudioDesign;
use App\Models\StudioTemplate;
use App\Models\User;
use App\Services\Studio\Merkkit;
use Database\Seeders\DemoSeeder;
use Database\Seeders\StudioSjablonenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

const MERK_APP = 'http://app.mebranding.test';

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->seed(StudioSjablonenSeeder::class);
    $this->eva = User::where('email', 'eva@demo.mebranding.nl')->first();
    $this->baas = User::factory()->create();
    $this->baas->assignRole('Super Admin');
    $this->lagen = fn (StudioDesign|StudioTemplate $d) => collect($d->pages)->flatMap(fn ($p) => $p['lagen']);
});

test('merkbasis per formaat: logo vast binnen de marges en buiten de veilige zones, tekst op stijl', function () {
    $kit = app(Merkkit::class);
    foreach (array_keys(config('studio.formaten')) as $formaat) {
        foreach (array_keys(Merkkit::BASISSEN) as $basis) {
            $f = $kit->formaat($formaat);
            $paginas = $kit->basis($formaat, $basis);
            expect(count($paginas))->toBe($formaat === 'carrousel' ? 3 : 1);
            $lagen = collect($paginas[0]['lagen']);

            $logo = $lagen->firstWhere('soort', 'logo');
            expect($logo['merk'])->toBeTrue()->and($logo['vergrendeld'])->toBeTrue()
                ->and($logo['x'])->toBeGreaterThanOrEqual($f['marge'])
                ->and($logo['y'] + $logo['hoogte'])->toBeLessThanOrEqual($f['hoogte'] - max($f['marge'], $f['veilig_onder']));

            $kop = $lagen->firstWhere('stijl', 'kop');
            expect($kop['grootte'])->toBe((int) round(80 * $f['schaal']))->and($kop['y'])->toBeGreaterThanOrEqual(max($f['marge'], $f['veilig_boven']));
            expect($lagen->firstWhere('stijl', 'tekst'))->not->toBeNull();
            // Minimale lettergrootte geldt per 1080 breed.
            expect($lagen->where('soort', 'tekst')->every(fn ($l) => $l['grootte'] * 1080 / $f['breedte'] >= 28))->toBeTrue();
        }
    }
});

test('de 8 huisstijlsjablonen: merkelementen en vakken vast, tekst op stijl, hooguit 3 stijlen per pagina', function () {
    $sjablonen = StudioTemplate::whereNotNull('slug')->get();
    expect($sjablonen)->toHaveCount(8);

    foreach ($sjablonen as $s) {
        expect($s->seed_version)->toBe(StudioSjablonenSeeder::VERSIE);
        $f = app(Merkkit::class)->formaat($s->format);
        foreach ($s->pages as $pagina) {
            $lagen = collect($pagina['lagen']);
            expect($lagen->where('soort', 'logo'))->not->toBeEmpty("{$s->name} heeft geen logo");
            expect($lagen->where('soort', 'logo')->every(fn ($l) => $l['merk'] && $l['vergrendeld']))->toBeTrue();
            expect($lagen->filter(fn ($l) => in_array($l['soort'], ['foto', 'video']) && ($l['vak'] ?? false))->every(fn ($l) => $l['vergrendeld']))->toBeTrue();
            $teksten = $lagen->where('soort', 'tekst');
            expect($teksten->every(fn ($l) => in_array($l['stijl'] ?? null, Merkkit::STIJLEN)))->toBeTrue("{$s->name}: tekst zonder stijl");
            expect($teksten->pluck('stijl')->unique()->count())->toBeLessThanOrEqual(3, "{$s->name}: te veel stijlen");
            // Tekst en logo binnen de marges en (Story) buiten de veilige zones.
            foreach ($lagen->whereIn('soort', ['tekst', 'logo']) as $l) {
                expect($l['x'])->toBeGreaterThanOrEqual($f['marge'])->and($l['x'] + $l['breedte'])->toBeLessThanOrEqual($f['breedte'] - $f['marge'])
                    ->and($l['y'])->toBeGreaterThanOrEqual(max($f['marge'], $f['veilig_boven']), "{$s->name}: {$l['naam']} te hoog");
            }
        }
    }
});

test('een ouder huisstijlsjabloon wordt bijgewerkt, de naam van het team blijft; eigen sjablonen niet aangeraakt', function () {
    $oud = StudioTemplate::where('slug', 'aankondiging')->sole();
    $oud->update(['name' => 'Onze aankondiging', 'seed_version' => 1, 'pages' => [['achtergrond' => '#FFFFFF', 'lagen' => []]]]);
    $eigen = StudioTemplate::create(['name' => 'Eigen', 'format' => 'instagram_post', 'width' => 1080, 'height' => 1080, 'pages' => [['achtergrond' => '#123456', 'lagen' => []]]]);

    $this->seed(StudioSjablonenSeeder::class);

    expect($oud->fresh()->name)->toBe('Onze aankondiging')->and($oud->fresh()->seed_version)->toBe(StudioSjablonenSeeder::VERSIE)
        ->and(count($oud->fresh()->pages[0]['lagen']))->toBeGreaterThan(3)
        ->and($eigen->fresh()->pages[0]['achtergrond'])->toBe('#123456');
});

test('merkslot: een teamlid kan logo en vak-kader niet verplaatsen; de inhoud van een vak wel; Super Admin wel alles', function () {
    $sjabloon = StudioTemplate::where('slug', 'creator-in-de-spotlight')->sole();
    $this->actingAs($this->eva)->post(MERK_APP."/team/studio/sjablonen/{$sjabloon->id}/gebruiken");
    $ontwerp = StudioDesign::sole();
    $url = MERK_APP."/team/studio/ontwerpen/{$ontwerp->id}";
    $paginas = $ontwerp->pages;
    $logo = collect($paginas[0]['lagen'])->search(fn ($l) => $l['soort'] === 'logo');
    $vak = collect($paginas[0]['lagen'])->search(fn ($l) => $l['soort'] === 'foto');

    // Logo verplaatsen of weghalen: geweigerd.
    $verplaatst = $paginas;
    $verplaatst[0]['lagen'][$logo]['x'] = 400;
    $this->actingAs($this->eva)->putJson($url, ['name' => 'x', 'pages' => $verplaatst])->assertStatus(422)->assertJsonValidationErrors('pages');
    $zonder = $paginas;
    array_splice($zonder[0]['lagen'], $logo, 1);
    $this->actingAs($this->eva)->putJson($url, ['name' => 'x', 'pages' => $zonder])->assertStatus(422);

    // Kader van het fotovak verschuiven of smaller maken: geweigerd (het probleem van Dave: X -346).
    $kader = $paginas;
    $kader[0]['lagen'][$vak]['x'] = -346;
    $kader[0]['lagen'][$vak]['breedte'] = 700;
    $this->actingAs($this->eva)->putJson($url, ['name' => 'x', 'pages' => $kader])->assertStatus(422);

    // Foto in het vak zetten en de uitsnede verschuiven: mag.
    $inhoud = $paginas;
    $inhoud[0]['lagen'][$vak]['bron'] = ['soort' => 'media', 'id' => 1];
    $inhoud[0]['lagen'][$vak]['uitsnede'] = ['x' => 0.3, 'y' => 0.6, 'zoom' => 1.4];
    $this->actingAs($this->eva)->putJson($url, ['name' => 'x', 'pages' => $inhoud])->assertOk();
    $opgeslagen = $ontwerp->fresh()->pages[0]['lagen'][$vak];
    expect($opgeslagen['x'])->toBe(60)->and($opgeslagen['breedte'])->toBe(960)->and($opgeslagen['uitsnede']['zoom'])->toBe(1.4);

    // Super Admin mag het merkslot openen.
    $this->actingAs($this->baas)->putJson($url, ['name' => 'x', 'pages' => $verplaatst])->assertOk();
});

test('tekststijl volgt de merkkit; Super Admin past de merkkit aan en elke wijziging staat in het logboek', function () {
    $this->actingAs($this->eva)->post(MERK_APP.'/team/studio/ontwerpen', ['formaat' => 'instagram_post', 'basis' => 'licht']);
    $ontwerp = StudioDesign::sole();

    // Een teamlid ziet de merkkit, maar past hem niet aan.
    $this->actingAs($this->eva)->get(MERK_APP.'/team/studio/merkkit')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('team/studio/merkkit')->where('magBewerken', false)->has('merkkit.palet', 8));
    $kit = app(Merkkit::class)->huidig();
    $data = array_merge($kit, ['formaten' => $kit['formaten']]);
    $this->actingAs($this->eva)->put(MERK_APP.'/team/studio/merkkit', $data)->assertForbidden();

    $data['stijlen']['kop']['grootte'] = 96;
    $data['palet'][] = ['kleur' => '#FFCC00', 'naam' => 'Geel'];
    $this->actingAs($this->baas)->put(MERK_APP.'/team/studio/merkkit', $data)->assertSessionHasNoErrors();

    $log = ActivityLog::where('event', 'merkkit_gewijzigd')->sole();
    expect($log->user_id)->toBe($this->baas->id)
        ->and($log->data['wijzigingen']['stijlen.kop.grootte'])->toBe(['oud' => 80, 'nieuw' => 96])
        ->and($log->data['wijzigingen']['palet.8.kleur']['nieuw'])->toBe('#FFCC00');

    // Bestaande ontwerpen volgen de nieuwe kop bij het laden.
    $this->actingAs($this->eva)->get(MERK_APP."/team/studio/ontwerpen/{$ontwerp->id}")
        ->assertInertia(fn (AssertableInertia $p) => $p->where('merkkit.stijlen.kop.grootte', 96)
            ->where('item.pages.0.lagen', fn ($lagen) => collect($lagen)->firstWhere('stijl', 'kop')['grootte'] === 96));

    // Lettertypes liggen vast; ongeldige waarden geweigerd.
    $data['stijlen']['kop']['lettertype'] = 'Comic Sans';
    $this->actingAs($this->baas)->put(MERK_APP.'/team/studio/merkkit', $data)->assertSessionHasErrors('stijlen.kop.lettertype');
});

test('toch exporteren ondanks rode merkcontrole: alleen Super Admin, met reden, in het logboek', function () {
    $this->actingAs($this->eva)->post(MERK_APP.'/team/studio/ontwerpen', ['formaat' => 'instagram_post', 'basis' => 'donker']);
    $ontwerp = StudioDesign::sole();

    $this->actingAs($this->eva)->postJson(MERK_APP."/team/studio/ontwerpen/{$ontwerp->id}/merkcontrole", ['reden' => 'Moet nu weg'])->assertForbidden();
    $this->actingAs($this->baas)->postJson(MERK_APP."/team/studio/ontwerpen/{$ontwerp->id}/merkcontrole", ['reden' => 'x'])->assertStatus(422);
    $this->actingAs($this->baas)->postJson(MERK_APP."/team/studio/ontwerpen/{$ontwerp->id}/merkcontrole", ['reden' => 'Campagne van de partner, logo zit al in de foto', 'bevindingen' => ['geen logo']])->assertOk();

    $log = ActivityLog::where('event', 'merkcontrole_overgeslagen')->sole();
    expect($log->reason)->toContain('Campagne')->and($log->subject_id)->toBe($ontwerp->id)->and($log->data['bevindingen'])->toBe(['geen logo']);
});
