<?php

use App\Models\Certificate;
use App\Models\CourseDay;
use App\Models\Creator;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PlatformMelding;
use App\Services\Academy;
use Database\Seeders\DemoAcademySeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DemoSeeder::class);
    $this->seed(DemoAcademySeeder::class);
    $this->app_ = 'http://app.mebranding.test';
    $this->dag = CourseDay::sole();
    $this->lisa = User::where('email', 'lisa@demo.mebranding.nl')->sole();
    $this->mirthe = User::where('email', 'mirthe@demo.mebranding.nl')->sole();
    $this->student = function (string $naam) {
        $u = User::create(['name' => $naam, 'email' => Str::slug($naam).'@voorbeeld.nl', 'password' => 'geheim-wachtwoord']);
        $u->markEmailAsVerified();
        $u->assignRole('Student');

        return $u;
    };
    $this->gegevens = ['phone' => '0612345678', 'city' => 'Franeker', 'tiktok' => '@test', 'instagram' => '', 'birthdate' => '2000-01-01', 'akkoord' => '1'];
});

test('de student ziet Mijn academy en de lessen, het team ziet de cursusdagen; anderen niet', function () {
    $this->actingAs($this->lisa)->get($this->app_.'/academy')->assertOk()->assertInertia(fn ($p) => $p->component('academy/overzicht')->has('enrollments', 1)->has('lessons', 3)->has('assignments', 2));
    $this->actingAs($this->lisa)->get($this->app_.'/team/academy')->assertForbidden();
    $this->actingAs($this->mirthe)->get($this->app_.'/team/academy')->assertOk()->assertInertia(fn ($p) => $p->component('team/academy/cursusdagen'));
    $this->actingAs($this->mirthe)->get($this->app_.'/team/academy/cursusdagen/'.$this->dag->id)->assertOk();
    $eva = User::where('email', 'eva@demo.mebranding.nl')->sole();
    $this->actingAs($eva)->get($this->app_.'/team/academy')->assertForbidden();
});

test('vol is wachtlijst, en bij afmelden schuift de eerste op de wachtlijst door met melding plek vrij', function () {
    Notification::fake();
    $this->dag->update(['max_participants' => 2]); // Lisa staat er al
    $anna = ($this->student)('Anna de Boer');
    $bram = ($this->student)('Bram Kuipers');
    $cees = ($this->student)('Cees Dijkstra');

    $this->actingAs($anna)->post($this->app_."/academy/cursusdagen/{$this->dag->id}/inschrijven", $this->gegevens)->assertSessionHasNoErrors();
    $this->actingAs($bram)->post($this->app_."/academy/cursusdagen/{$this->dag->id}/inschrijven", $this->gegevens)->assertSessionHasNoErrors();
    $this->actingAs($cees)->post($this->app_."/academy/cursusdagen/{$this->dag->id}/inschrijven", $this->gegevens)->assertSessionHasNoErrors();

    $status = fn (User $u) => Enrollment::where('user_id', $u->id)->sole();
    expect($status($anna)->status)->toBe(Enrollment::INGESCHREVEN)
        ->and($status($bram)->status)->toBe(Enrollment::WACHTLIJST)->and($status($bram)->plek())->toBe(1)
        ->and($status($cees)->plek())->toBe(2);
    Notification::assertSentTo($bram, PlatformMelding::class, fn ($n) => $n->template->key === 'a-wachtlijst');

    // Anna meldt zich af: Bram krijgt de plek met een betaaltermijn, Cees schuift op naar plek 1
    $this->actingAs($anna)->post($this->app_."/academy/inschrijvingen/{$status($anna)->id}/afmelden")->assertSessionHasNoErrors();
    expect($status($bram)->status)->toBe(Enrollment::INGESCHREVEN)
        ->and($status($bram)->offer_expires_at)->not->toBeNull()
        ->and($status($cees)->plek())->toBe(1)
        ->and($this->dag->fresh()->bezet())->toBe(2);
    Notification::assertSentTo($bram, PlatformMelding::class, fn ($n) => $n->template->key === 'a-plek-vrij');

    // Meer plekken door het team: ook Cees schuift door
    $this->actingAs($this->mirthe)->put($this->app_."/team/academy/cursusdagen/{$this->dag->id}", [
        'title' => $this->dag->title, 'date' => $this->dag->date->toDateString(), 'starts_at' => '10:00', 'ends_at' => '16:00',
        'location_name' => $this->dag->location_name, 'city' => $this->dag->city, 'max_participants' => 3, 'price_euro' => 295, 'exam_required' => true,
    ])->assertSessionHasNoErrors();
    expect($status($cees)->status)->toBe(Enrollment::INGESCHREVEN);

    // Betaalstatus zet het team handmatig (betaalpartij nog niet gekozen)
    $this->actingAs($this->mirthe)->post($this->app_."/team/academy/inschrijvingen/{$status($bram)->id}/betaald", ['paid' => true])->assertSessionHasNoErrors();
    expect($status($bram)->paid_status)->toBe(Enrollment::BETAALD)->and($status($bram)->offer_expires_at)->toBeNull();
});

test('check-in kan alleen op de dag zelf, met de knop of de QR-code, en niet dubbel', function () {
    $lisa = Enrollment::where('user_id', $this->lisa->id)->sole();

    $this->actingAs($this->lisa)->post($this->app_."/academy/inschrijvingen/{$lisa->id}/ik-ben-er")->assertSessionHasErrors('checkin');
    $this->actingAs($this->mirthe)->get($this->app_."/team/academy/checkin/{$lisa->checkin_code}")->assertSessionHasErrors('checkin');
    expect($lisa->fresh()->checked_in_at)->toBeNull();

    $this->travelTo($this->dag->date->copy()->setTime(9, 51));
    $this->actingAs($this->lisa)->post($this->app_."/academy/inschrijvingen/{$lisa->id}/ik-ben-er")->assertSessionHasNoErrors();
    expect($lisa->fresh()->status)->toBe(Enrollment::AANWEZIG);
    $this->actingAs($this->mirthe)->get($this->app_."/team/academy/checkin/{$lisa->checkin_code}")->assertSessionHasErrors('checkin'); // al ingecheckt

    // Via de QR-code (team) voor een tweede deelnemer
    $anna = ($this->student)('Anna de Boer');
    $e = app(Academy::class)->inschrijven($this->dag, $anna);
    $this->actingAs($this->mirthe)->get($this->app_."/team/academy/checkin/{$e->checkin_code}")->assertRedirect();
    expect($e->fresh()->status)->toBe(Enrollment::AANWEZIG);

    // Een andere student kan niet voor Lisa inchecken
    $this->travelTo($this->dag->date->copy()->addDay()->setTime(10, 0));
    $this->actingAs($anna)->post($this->app_."/academy/inschrijvingen/{$lisa->id}/ik-ben-er")->assertForbidden();
});

test('examen, certificaat met unieke controlelink, en doorstroom maakt een creator in screening', function () {
    Notification::fake();
    $lisa = Enrollment::where('user_id', $this->lisa->id)->sole();
    $this->travelTo($this->dag->date->copy()->setTime(10, 0));
    app(Academy::class)->inchecken($lisa, $this->mirthe);
    $anna = ($this->student)('Anna de Boer');
    app(Academy::class)->inschrijven($this->dag, $anna);

    // Dag afsluiten: Anna niet verschenen, Lisa krijgt het praktijkexamen
    $this->actingAs($this->mirthe)->post($this->app_."/team/academy/cursusdagen/{$this->dag->id}/afsluiten")->assertSessionHasNoErrors();
    expect(Enrollment::where('user_id', $anna->id)->sole()->status)->toBe(Enrollment::AFWEZIG);
    $examen = Exam::sole();

    // Zonder certificaat geen doorstroom
    $this->actingAs($this->lisa)->post($this->app_.'/academy/word-creator')->assertSessionHasErrors('creator');

    $this->actingAs($this->lisa)->post($this->app_."/academy/examen/{$examen->id}", [
        'items' => [
            ['video' => UploadedFile::fake()->create('v1.mp4', 500, 'video/mp4')],
            ['link' => 'https://www.tiktok.com/@lisa.filmt/video/1'],
            ['link' => 'https://www.tiktok.com/@lisa.filmt/video/2'],
        ],
        'eigen_werk' => '1', 'toestemming' => '1',
    ])->assertSessionHasNoErrors();
    expect($examen->fresh()->status)->toBe(Exam::INGELEVERD)->and($examen->submissions()->count())->toBe(3);

    // Een 1 betekent verbeteren, ook bij een goed gemiddelde
    $slecht = ['Hook' => 5, 'Licht' => 5, 'Geluid' => 1, 'Beeld' => 5, 'Boodschap' => 5, 'CTA' => 5];
    $this->actingAs($this->mirthe)->post($this->app_."/team/academy/examens/{$examen->id}/beoordelen", ['scores' => $slecht])->assertSessionHasNoErrors();
    expect($examen->fresh()->status)->toBe(Exam::VERBETEREN)->and(Certificate::count())->toBe(0);

    // Herkansing: voldoende, certificaat
    $this->actingAs($this->lisa)->post($this->app_."/academy/examen/{$examen->id}", [
        'items' => [['link' => 'https://example.com/1'], ['link' => 'https://example.com/2'], ['link' => 'https://example.com/3']],
        'eigen_werk' => '1', 'toestemming' => '1',
    ])->assertSessionHasNoErrors();
    $goed = ['Hook' => 4, 'Licht' => 4, 'Geluid' => 3, 'Beeld' => 4, 'Boodschap' => 4, 'CTA' => 4];
    $this->actingAs($this->mirthe)->post($this->app_."/team/academy/examens/{$examen->id}/beoordelen", ['scores' => $goed, 'feedback' => 'Sterke hook.'])->assertSessionHasNoErrors();

    $certificaat = Certificate::sole();
    expect($examen->fresh()->status)->toBe(Exam::VOLDOENDE)->and($examen->fresh()->attempt)->toBe(2)
        ->and($lisa->fresh()->status)->toBe(Enrollment::GESLAAGD)
        ->and($certificaat->number)->toMatch('/^MB-ACA-\d{4}-\d{6}$/')
        ->and(strlen($certificaat->verification_code))->toBe(32);
    Notification::assertSentTo($this->lisa, PlatformMelding::class, fn ($n) => $n->template->key === 'a-certificaat');
    Notification::assertSentTo($this->lisa, PlatformMelding::class, fn ($n) => $n->template->key === 'a-word-creator');

    // Publieke controlelink zonder inloggen; een verzonnen code geeft niets
    auth()->logout();
    $this->get('http://mebranding.test/certificaat/'.$certificaat->verification_code)->assertOk()->assertSee('Dit certificaat is echt')->assertSee($certificaat->number)->assertSee('Lisa Hoekstra');
    $this->get('http://mebranding.test/certificaat/'.Str::random(32))->assertOk()->assertSee('Certificaat niet gevonden')->assertDontSee('Lisa Hoekstra');
    expect($certificaat->controleUrl())->toBe('http://mebranding.test/certificaat/'.$certificaat->verification_code);

    // Een tweede certificaat krijgt een ander nummer en een andere code
    $this->travelTo($this->dag->date->copy()->setTime(10, 0));
    $dag2 = CourseDay::create($this->dag->only(['title', 'starts_at', 'ends_at', 'location_name', 'city', 'max_participants', 'price_cents']) + ['date' => $this->dag->date, 'exam_required' => false, 'status' => CourseDay::OPEN]);
    $bram = ($this->student)('Bram Kuipers');
    $e2 = app(Academy::class)->inschrijven($dag2, $bram);
    app(Academy::class)->inchecken($e2, $this->mirthe);
    app(Academy::class)->dagAfsluiten($dag2, $this->mirthe); // zonder examen: direct certificaat
    $tweede = Certificate::where('user_id', $bram->id)->sole();
    expect($tweede->number)->not->toBe($certificaat->number)->and($tweede->verification_code)->not->toBe($certificaat->verification_code);

    // Certificaat bekijken: alleen de eigenaar
    $this->actingAs($this->lisa)->get($this->app_."/academy/certificaat/{$certificaat->id}")->assertOk()->assertSee($certificaat->number);
    $this->actingAs($bram)->get($this->app_."/academy/certificaat/{$certificaat->id}")->assertForbidden();

    // Doorstroom: creator in screening, Bronze, rol Creator erbij, Student blijft, screeningstaak in de werkvoorraad
    $this->actingAs($this->lisa)->post($this->app_.'/academy/word-creator')->assertSessionHasNoErrors();
    $creator = Creator::where('user_id', $this->lisa->id)->sole();
    $lisaUser = $this->lisa->fresh();
    expect($creator->status)->toBe('screening')->and($creator->level)->toBe('bronze')->and($creator->tiktok)->toBe('lisa.filmt')
        ->and($lisaUser->hasRole('Creator'))->toBeTrue()->and($lisaUser->hasRole('Student'))->toBeTrue();
    $taak = Task::where('type', 'screening')->where('creator_id', $creator->id)->sole();
    expect($taak->permission)->toBe('creators.screenen')->and($taak->closed_at)->toBeNull();

    // Nog eens drukken maakt geen tweede creator; de academy blijft bereikbaar
    $this->actingAs($lisaUser)->post($this->app_.'/academy/word-creator');
    expect(Creator::where('user_id', $this->lisa->id)->count())->toBe(1);
    $this->actingAs($lisaUser)->get($this->app_.'/academy')->assertOk();
});
