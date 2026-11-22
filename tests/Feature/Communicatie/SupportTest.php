<?php

use App\Models\SupportVraag;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NieuwBericht;
use Database\Seeders\DemoSeeder;
use Database\Seeders\HelpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([DemoSeeder::class, HelpSeeder::class]);
    $this->url = 'http://app.mebranding.test';
    $wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
    [$this->sanne, $this->eva, $this->mirthe, $this->sophie] = [$wie('sanne'), $wie('eva'), $wie('mirthe'), $wie('sophie')];
});

test('helpartikelen per doelgroep, met zoeken', function () {
    $titels = fn ($user, $omgeving, $zoek = null) => collect($this->actingAs($user)->get("{$this->url}/{$omgeving}/help".($zoek ? "?zoek={$zoek}" : ''))->assertOk()
        ->viewData('page')['props']['articles'])->pluck('title');

    expect($titels($this->sanne, 'ondernemer'))->toContain('Hoe werken tegoed en punten?')->not->toContain('Hoe lever ik mijn video aan?')
        ->and($titels($this->sophie, 'creator'))->toContain('Hoe lever ik mijn video aan?')->not->toContain('Hoe werken tegoed en punten?')
        ->and($titels($this->sophie, 'creator', 'wachtwoord')->all())->toBe(['Account en wachtwoord']);
});

test('supportvraag: indienen, werkvoorraad, beantwoorden met recht, reageren opent weer', function () {
    Notification::fake();

    $this->actingAs($this->sanne)->post("{$this->url}/ondernemer/help/vraag", ['subject' => 'Tweede gebruiker', 'body' => 'Kan mijn collega ook inloggen?', 'priority' => 'normaal'])
        ->assertSessionHasNoErrors();
    $vraag = SupportVraag::sole();
    expect($vraag->status)->toBe('open')->and($vraag->number)->toStartWith('MB-S-')
        ->and($vraag->assigned_to)->toBe($this->eva->id) // accountmanager van Bloesem
        ->and(Task::open()->where('type', 'support')->where('permission', 'support.beheren')->count())->toBe(1);
    Notification::assertSentTo($this->eva, NieuwBericht::class);

    // Zonder het recht support.beheren geen werkbak en geen antwoord (Mirthe heeft Sales).
    $this->actingAs($this->mirthe)->get("{$this->url}/team/support")->assertForbidden();
    $this->actingAs($this->mirthe)->post("{$this->url}/team/support/{$vraag->id}/antwoord", ['body' => 'Hoi'])->assertForbidden();
    // Een ander ziet de vraag niet.
    $this->actingAs($this->sophie)->get("{$this->url}/creator/help/vragen/{$vraag->id}")->assertForbidden();

    // Interne notitie: de vraagsteller ziet hem niet, de vraag blijft open.
    $this->actingAs($this->eva)->post("{$this->url}/team/support/{$vraag->id}/antwoord", ['body' => 'Eerst Sneek checken', 'internal' => true])->assertSessionHasNoErrors();
    expect($vraag->fresh()->status)->toBe('open');

    $this->actingAs($this->eva)->get("{$this->url}/team/support/{$vraag->id}")->assertOk();
    $this->actingAs($this->eva)->post("{$this->url}/team/support/{$vraag->id}/antwoord", ['body' => 'Ja, ik stuur je collega een uitnodiging.'])->assertSessionHasNoErrors();
    expect($vraag->fresh()->status)->toBe('beantwoord')
        ->and(Task::open()->where('type', 'support')->count())->toBe(0);
    Notification::assertSentTo($this->sanne, NieuwBericht::class, fn ($n) => str_contains($n->url, "/ondernemer/help/vragen/{$vraag->id}"));

    $replies = collect($this->actingAs($this->sanne)->get("{$this->url}/ondernemer/help/vragen/{$vraag->id}")->assertOk()->viewData('page')['props']['ticket']['replies'])->pluck('body');
    expect($replies->all())->not->toContain('Eerst Sneek checken')->toContain('Ja, ik stuur je collega een uitnodiging.');

    $this->actingAs($this->sanne)->post("{$this->url}/ondernemer/help/vragen/{$vraag->id}", ['body' => 'Dank je! Hij heet Tom.'])->assertSessionHasNoErrors();
    expect($vraag->fresh()->status)->toBe('open')->and(Task::open()->where('type', 'support')->count())->toBe(1);

    $this->actingAs($this->eva)->post("{$this->url}/team/support/{$vraag->id}", ['status' => 'gesloten', 'priority' => 'laag', 'assigned_to' => $this->eva->id])->assertSessionHasNoErrors();
    expect($vraag->fresh())->status->toBe('gesloten')->closed_at->not->toBeNull()
        ->and(Task::open()->where('type', 'support')->count())->toBe(0);
});

test('helpartikelen beheren alleen met support.beheren', function () {
    $artikel = ['title' => 'Nieuw artikel', 'body' => 'Tekst', 'audiences' => ['creator'], 'category' => 'Account'];
    $this->actingAs($this->mirthe)->post("{$this->url}/team/help/artikelen", $artikel)->assertForbidden();
    $this->actingAs($this->eva)->post("{$this->url}/team/help/artikelen", $artikel)->assertSessionHasNoErrors();
    expect($this->actingAs($this->sophie)->get("{$this->url}/creator/help?zoek=Nieuw%20artikel")->viewData('page')['props']['articles'])->toHaveCount(1);
});
