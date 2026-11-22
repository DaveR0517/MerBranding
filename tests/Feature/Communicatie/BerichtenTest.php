<?php

use App\Models\Bericht;
use App\Models\Company;
use App\Models\User;
use App\Notifications\NieuwBericht;
use App\Notifications\PlatformMelding;
use App\Services\Samenwerking;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->url = 'http://app.mebranding.test';
    $wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
    [$this->sanne, $this->eva, $this->sophie, $this->marit] = [$wie('sanne'), $wie('eva'), $wie('sophie'), $wie('marit')];

    $keten = app(Samenwerking::class);
    $bloesem = Company::first();
    $this->c = $keten->aanvragen($bloesem, $this->sanne, [
        'location_id' => $bloesem->locations()->first()->id, 'title' => 'Wintersoep', 'briefing' => 'Laat de soep zien.',
        'alcohol' => false, 'creator_level' => 'elite',
    ]);
    $keten->accepteren($keten->uitnodigen($this->c, $this->sophie->creator, $this->eva), $this->sophie, 'Sophie de Vries', '127.0.0.1');
    $this->c->refresh();
});

function gesprekProps($test, User $user, string $omgeving): array
{
    $props = null;
    $test->actingAs($user)->get("{$test->url}/{$omgeving}/berichten/{$test->c->id}")->assertOk()
        ->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

    return $props['conversation'];
}

test('twee kanalen per samenwerking: de ondernemer ziet het creatorkanaal niet, de creator het ondernemerskanaal niet', function () {
    $this->actingAs($this->sanne)->post("{$this->url}/ondernemer/berichten/{$this->c->id}", ['channel' => 'team_ondernemer', 'body' => 'Vraag van Sanne'])->assertSessionHasNoErrors();
    $this->actingAs($this->eva)->post("{$this->url}/team/berichten/{$this->c->id}", ['channel' => 'team_creator', 'body' => 'Briefing voor Sophie'])->assertSessionHasNoErrors();

    $ondernemer = gesprekProps($this, $this->sanne, 'ondernemer');
    expect(collect($ondernemer['channels'])->pluck('key')->all())->toBe(['team_ondernemer'])
        ->and(collect($ondernemer['channels'][0]['messages'])->pluck('body')->all())->toBe(['Vraag van Sanne']);

    $creator = gesprekProps($this, $this->sophie, 'creator');
    expect(collect($creator['channels'])->pluck('key')->all())->toBe(['team_creator'])
        ->and(collect($creator['channels'][0]['messages'])->pluck('body')->all())->toBe(['Briefing voor Sophie']);

    $team = gesprekProps($this, $this->eva, 'team');
    expect(collect($team['channels'])->pluck('key')->all())->toBe(['team_ondernemer', 'team_creator']);

    // Schrijven in een kanaal dat niet van jou is, mag niet.
    $this->actingAs($this->sanne)->post("{$this->url}/ondernemer/berichten/{$this->c->id}", ['channel' => 'team_creator', 'body' => 'Hoi Sophie'])->assertSessionHasErrors('channel');
    $this->actingAs($this->sanne)->post("{$this->url}/ondernemer/berichten/{$this->c->id}", ['channel' => 'alle', 'body' => 'Hoi Sophie'])->assertSessionHasErrors('channel');
    expect(Bericht::where('body', 'Hoi Sophie')->exists())->toBeFalse();
});

test('met de instelling aan is er een gezamenlijk gesprek voor ondernemer, creator en team', function () {
    config(['mebranding.berichten.ondernemer_naar_creator' => true]);

    $this->actingAs($this->sanne)->post("{$this->url}/ondernemer/berichten/{$this->c->id}", ['channel' => 'alle', 'body' => 'Hoi Sophie, welkom!'])->assertSessionHasNoErrors();
    $this->actingAs($this->eva)->post("{$this->url}/team/berichten/{$this->c->id}", ['channel' => 'team_creator', 'body' => 'Alleen voor Sophie'])->assertSessionHasNoErrors();

    $creator = gesprekProps($this, $this->sophie, 'creator');
    $alle = collect($creator['channels'])->firstWhere('key', 'alle');
    expect(collect($alle['messages'])->pluck('body')->all())->toBe(['Hoi Sophie, welkom!']);

    $ondernemer = gesprekProps($this, $this->sanne, 'ondernemer');
    expect(collect($ondernemer['channels'])->pluck('key')->all())->toBe(['team_ondernemer', 'alle'])
        ->and(collect($ondernemer['channels'])->flatMap(fn ($k) => $k['messages'])->pluck('body')->all())->not->toContain('Alleen voor Sophie');

    $this->actingAs($this->sophie)->post("{$this->url}/creator/berichten/{$this->c->id}", ['channel' => 'alle', 'body' => 'Dank je, Sanne!'])->assertSessionHasNoErrors();
});

test('alleen betrokkenen: een andere creator komt het gesprek niet in', function () {
    $this->actingAs($this->marit)->get("{$this->url}/creator/berichten/{$this->c->id}")->assertForbidden();
    $this->actingAs($this->marit)->post("{$this->url}/creator/berichten/{$this->c->id}", ['channel' => 'team_creator', 'body' => 'x'])->assertForbidden();
    $this->actingAs($this->sanne)->get("{$this->url}/team/berichten")->assertForbidden();
});

test('melding bij een nieuw bericht: team naar ondernemer met o-vraag, de rest in de app; ongelezen telt tot het openen', function () {
    Notification::fake();

    $this->actingAs($this->eva)->post("{$this->url}/team/berichten/{$this->c->id}", ['channel' => 'team_ondernemer', 'body' => 'Mag Sophie om 14:00 komen?']);
    Notification::assertSentTo($this->sanne, PlatformMelding::class, fn ($n) => $n->template->key === 'o-vraag' && str_contains($n->url, "/ondernemer/berichten/{$this->c->id}"));

    $this->actingAs($this->sophie)->post("{$this->url}/creator/berichten/{$this->c->id}", ['channel' => 'team_creator', 'body' => 'Ik neem een lamp mee.']);
    Notification::assertSentTo($this->eva, NieuwBericht::class, fn ($n) => str_contains($n->url, "/team/berichten/{$this->c->id}"));
    Notification::assertNotSentTo($this->sanne, NieuwBericht::class);

    $inbox = fn () => $this->actingAs($this->sanne)->get("{$this->url}/ondernemer/berichten")->assertOk()->viewData('page')['props']['conversations'];
    expect($inbox()[0]['unread'])->toBe(1);
    $this->actingAs($this->sanne)->get("{$this->url}/ondernemer/berichten/{$this->c->id}")->assertOk();
    expect($inbox()[0]['unread'])->toBe(0);
});
