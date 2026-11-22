<?php

use App\Events\SamenwerkingGewijzigd;
use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Company;
use App\Models\CreatorProfiel;
use App\Models\Signature;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app_ = 'http://app.mebranding.test';
    $this->seed(DemoSeeder::class);
    $this->wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
    $this->klus = function (User $wie, array $extra = []) {
        $bedrijf = Company::first();

        return Collaboration::create($extra + [
            'number' => 'MB-T-'.random_int(1000, 9999), 'company_id' => $bedrijf->id, 'location_id' => $bedrijf->locations()->first()->id,
            'creator_id' => $wie->creator->id, 'title' => 'Kerstlunch', 'briefing' => 'Laat de kerstlunch zien.', 'status' => Status::BezoekGepland,
        ]);
    };
});

test('uitbetaalgegevens worden versleuteld opgeslagen en het IBAN wordt gecontroleerd', function () {
    $sophie = ($this->wie)('sophie');

    $this->actingAs($sophie)->post($this->app_.'/creator/account', ['iban' => 'NL91ABNA0417164301', 'name' => 'S. de Vries', 'vat_status' => 'kor'])
        ->assertSessionHasErrors('iban');
    $this->actingAs($sophie)->post($this->app_.'/creator/account', ['iban' => 'NL91 ABNA 0417 1643 00', 'name' => 'S. de Vries', 'vat_status' => 'btw'])
        ->assertSessionHasErrors('vat_number');

    $this->actingAs($sophie)->post($this->app_.'/creator/account', [
        'iban' => 'nl91 abna 0417 1643 00', 'name' => 'S. de Vries', 'vat_status' => 'btw', 'vat_number' => 'NL123456789B01',
    ])->assertSessionHasNoErrors();

    $ruw = DB::table('creators')->where('id', $sophie->creator->id)->value('payout_details');
    expect($ruw)->not->toContain('NL91')->not->toContain('Vries');

    $creator = $sophie->creator->fresh();
    expect(CreatorProfiel::uitbetaling($creator))->toBe(['iban' => 'NL91ABNA0417164300', 'name' => 'S. de Vries', 'vat_number' => 'NL123456789B01'])
        ->and($creator->vat_status)->toBe('btw')
        ->and(CreatorProfiel::uitbetalingCompleet($creator))->toBeTrue();

    // Leeg IBAN houdt het bewaarde nummer; het scherm toont het maar half.
    $this->actingAs($sophie)->post($this->app_.'/creator/account', ['iban' => '', 'name' => 'Sophie de Vries', 'vat_status' => 'geen'])->assertSessionHasNoErrors();
    expect(CreatorProfiel::uitbetaling($creator->fresh()))->toBe(['iban' => 'NL91ABNA0417164300', 'name' => 'Sophie de Vries']);
    $this->actingAs($sophie)->get($this->app_.'/creator/account')->assertOk()->assertDontSee('NL91ABNA0417164300')
        ->assertInertia(fn ($p) => $p->where('payout.iban_masked', 'NL91 •••• •••• 00'));
    expect(ActivityLog::where('event', 'uitbetaalgegevens_gewijzigd')->count())->toBe(2);
});

test('de raamovereenkomst wordt één keer getekend met naam, versie en ip', function () {
    $marit = ($this->wie)('marit');

    $this->actingAs($marit)->get($this->app_.'/creator/start')->assertOk();
    $this->actingAs($marit)->post($this->app_.'/creator/raamovereenkomst', ['signed_name' => 'Marit Postma'])->assertSessionHasErrors('akkoord');
    $this->actingAs($marit)->post($this->app_.'/creator/raamovereenkomst', ['signed_name' => 'Marit Postma', 'akkoord' => '1'])->assertSessionHasNoErrors();
    $this->actingAs($marit)->post($this->app_.'/creator/raamovereenkomst', ['signed_name' => 'Marit Postma', 'akkoord' => '1'])->assertSessionHasNoErrors();

    $handtekening = Signature::where('agreement', 'raamovereenkomst')->sole();
    expect($handtekening->user_id)->toBe($marit->id)
        ->and($handtekening->version)->toBe(CreatorProfiel::raamVersie())
        ->and($handtekening->signed_name)->toBe('Marit Postma')
        ->and($handtekening->ip)->not->toBeNull()
        ->and($handtekening->collaboration_id)->toBeNull()
        ->and(ActivityLog::where('event', 'raamovereenkomst_getekend')->count())->toBe(1);

    // Het team kan dit scherm niet gebruiken.
    $this->actingAs(($this->wie)('ruben'))->post($this->app_.'/creator/raamovereenkomst', ['signed_name' => 'R', 'akkoord' => '1'])->assertForbidden();
});

test('bij Live zonder uitbetaalgegevens krijgt een betaalde creator de melding gegevens ontbreken', function () {
    $sophie = ($this->wie)('sophie'); // Elite, vergoeding
    $marit = ($this->wie)('marit'); // Bronze, geen geld

    $betaald = ($this->klus)($sophie, ['fee_cents' => 16000, 'status' => Status::Live]);
    SamenwerkingGewijzigd::dispatch($betaald, Status::Goedgekeurd, Status::Live, $sophie);
    $melding = $sophie->notifications()->get()->firstWhere('data.key', 'c-gegevens-ontbreken');
    expect($melding)->not->toBeNull()->and($melding->data['url'])->toContain('/creator/account');

    // Andere overgang: geen melding.
    $sophie->notifications()->delete();
    SamenwerkingGewijzigd::dispatch($betaald, Status::TerControle, Status::Goedgekeurd, $sophie);
    expect($sophie->notifications()->count())->toBe(0);

    // Met gegevens: geen melding.
    $sophie->creator->update(['payout_details' => json_encode(['iban' => 'NL91ABNA0417164300', 'name' => 'S. de Vries']), 'vat_status' => 'kor']);
    SamenwerkingGewijzigd::dispatch($betaald->fresh(), Status::Goedgekeurd, Status::Live, $sophie);
    expect($sophie->notifications()->count())->toBe(0);

    // Bronze zonder vergoeding: geen melding.
    $gratis = ($this->klus)($marit, ['fee_cents' => 0, 'status' => Status::Live]);
    SamenwerkingGewijzigd::dispatch($gratis, Status::Goedgekeurd, Status::Live, $marit);
    expect($marit->notifications()->where('data->key', 'c-gegevens-ontbreken')->count())->toBe(0);
});

test('profiel opslaan met foto, niches en portfolio', function () {
    Storage::fake('public');
    $marit = ($this->wie)('marit');

    $this->actingAs($marit)->post($this->app_.'/creator/profiel', [
        'photo' => UploadedFile::fake()->image('ik.jpg', 400, 400),
        'bio' => 'Ik film eten en uitjes in Friesland.',
        'kind' => 'influencer', 'niches' => ['Eten en drinken', 'Uitjes en cultuur'], 'city' => 'Franeker', 'radius_km' => 40,
        'instagram' => '@marit.ontdekt', 'tiktok' => 'marit.ontdekt', 'portfolio_links' => ['https://marit.nl', ''],
    ])->assertSessionHasNoErrors();

    $creator = $marit->creator->fresh();
    $profiel = CreatorProfiel::van($creator);
    Storage::disk('public')->assertExists($profiel->photo_path);
    expect($creator->kind)->toBe('influencer')
        ->and($creator->niches)->toBe(['Eten en drinken', 'Uitjes en cultuur'])
        ->and($creator->tiktok)->toBe('@marit.ontdekt')
        ->and($profiel->portfolio_links)->toBe(['https://marit.nl'])
        ->and($profiel->radius_km)->toBe(40);

    $this->actingAs($marit)->post($this->app_.'/creator/profiel', ['kind' => 'gamer', 'city' => 'X', 'niches' => ['Onzin']])->assertSessionHasErrors(['kind', 'niches.0']);
    $this->actingAs($marit)->get($this->app_.'/creator/profiel')->assertOk();
});

test('agenda toont komende afspraken en levert een ics per afspraak, alleen voor de eigen klus', function () {
    $marit = ($this->wie)('marit');
    $klus = ($this->klus)($marit, ['visit_at' => now()->addDays(2)->setTime(14, 0), 'content_deadline_at' => now()->addDays(7)->setTime(17, 0)]);

    $this->actingAs($marit)->get($this->app_.'/creator/agenda')->assertOk()
        ->assertInertia(fn ($p) => $p->component('creator/profiel/agenda')->has('upcoming', 2));

    $ics = $this->actingAs($marit)->get($this->app_."/creator/agenda/{$klus->id}/bezoek.ics")->assertOk();
    expect($ics->headers->get('content-type'))->toContain('text/calendar')
        ->and($ics->getContent())->toContain('BEGIN:VEVENT')->toContain('SUMMARY:Bezoek: Lunchroom Bloesem')->toContain("\r\n");

    $this->actingAs($marit)->get($this->app_."/creator/agenda/{$klus->id}/publicatie.ics")->assertNotFound();
    $this->actingAs(($this->wie)('sophie'))->get($this->app_."/creator/agenda/{$klus->id}/bezoek.ics")->assertForbidden();
});

test('vergoedingen: bronze ziet gratis bezoek, elite ziet bedragen en een waarschuwing zonder gegevens', function () {
    $this->actingAs(($this->wie)('marit'))->get($this->app_.'/creator/vergoedingen')->assertOk()
        ->assertInertia(fn ($p) => $p->where('paid_level', false));

    $sophie = ($this->wie)('sophie');
    ($this->klus)($sophie, ['fee_cents' => 16000]);
    $this->actingAs($sophie)->get($this->app_.'/creator/vergoedingen')->assertOk()
        ->assertInertia(fn ($p) => $p->where('paid_level', true)->where('totals.waiting_cents', 16000)->where('payout_details.complete', false));
});
