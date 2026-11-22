<?php

use App\Models\Uitnodigingslink;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
    [$this->sanne, $this->sophie] = [$wie('sanne'), $wie('sophie')];
});

test('persoonlijke link: vast per gebruiker, de publieke route zet de cookie en stuurt door', function () {
    $pagina = $this->actingAs($this->sophie)->get('http://app.mebranding.test/creator/uitnodigen')->assertOk()->viewData('page')['props'];
    $code = Uitnodigingslink::where('user_id', $this->sophie->id)->value('code');
    expect($pagina['code'])->toBe($code)->and($pagina['link'])->toEndWith("/uitnodiging/{$code}");
    $this->actingAs($this->sophie)->get('http://app.mebranding.test/creator/uitnodigen');
    expect(Uitnodigingslink::count())->toBe(1);

    auth()->logout();
    $this->get("http://mebranding.test/uitnodiging/{$code}")->assertRedirect(route('site.creators'))->assertCookie(Uitnodigingslink::COOKIE, $code);

    $ondernemer = Uitnodigingslink::voor($this->sanne)->code;
    $this->get("http://mebranding.test/uitnodiging/{$ondernemer}")->assertRedirect(route('site.ondernemers'));
    $this->get('http://mebranding.test/uitnodiging/bestaat-niet')->assertRedirect(route('site.home'))->assertCookieMissing(Uitnodigingslink::COOKIE);
});
