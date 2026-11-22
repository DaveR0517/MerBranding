<?php

use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\LeadFase;
use App\Models\Uitnodigingslink;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->url = 'http://app.mebranding.test';
    $wie = fn (string $naam) => User::where('email', "{$naam}@demo.mebranding.nl")->first();
    [$this->mirthe, $this->ruben, $this->sanne] = [$wie('mirthe'), $wie('ruben'), $wie('sanne')];
    $this->lead = Lead::create(['type' => 'ondernemer', 'name' => 'Jelle Bosma', 'email' => 'jelle@kompas.test', 'company' => 'Koffiebar Het Kompas', 'city' => 'Leeuwarden']);
});

test('fase wijzigen in de pipeline zet een logregel', function () {
    $this->actingAs($this->mirthe)->get("{$this->url}/team/sales")->assertOk();
    $this->actingAs($this->mirthe)->post("{$this->url}/team/sales/{$this->lead->id}/fase", ['stage' => 'gebeld'])->assertSessionHasNoErrors();

    expect(LeadFase::where('lead_id', $this->lead->id)->first())->stage->toBe('gebeld')->owner_id->toBe($this->mirthe->id);
    $log = ActivityLog::where('subject_type', $this->lead->getMorphClass())->where('subject_id', $this->lead->id)->sole();
    expect($log)->event->toBe('sales_fase')->from_status->toBe('nieuw')->to_status->toBe('gebeld')->user_id->toBe($this->mirthe->id);

    // Klant worden gaat alleen via Bedrijf toevoegen.
    $this->actingAs($this->mirthe)->post("{$this->url}/team/sales/{$this->lead->id}/fase", ['stage' => 'klant'])->assertSessionHasErrors('stage');
    $this->lead->update(['status' => 'klant']);
    $lead = collect($this->actingAs($this->mirthe)->get("{$this->url}/team/sales")->viewData('page')['props']['leads'])->firstWhere('id', $this->lead->id);
    expect($lead['stage'])->toBe('klant')->and($lead['history'])->toHaveCount(1);
});

test('notitie en volgende actie komen in de historie', function () {
    $this->actingAs($this->mirthe)->post("{$this->url}/team/sales/{$this->lead->id}/notitie", ['body' => 'Belt terug na de vakantie.'])->assertSessionHasNoErrors();
    $this->actingAs($this->mirthe)->post("{$this->url}/team/sales/{$this->lead->id}", ['owner_id' => $this->mirthe->id, 'next_action' => 'Bellen', 'next_action_on' => today()->addDay()->toDateString()])->assertSessionHasNoErrors();

    $lead = collect($this->actingAs($this->mirthe)->get("{$this->url}/team/sales")->viewData('page')['props']['leads'])->firstWhere('id', $this->lead->id);
    expect($lead['next_action'])->toBe('Bellen')->and(collect($lead['history'])->pluck('event')->all())->toBe(['sales_actie', 'sales_notitie']);
});

test('alleen met sales.beheren', function () {
    $this->actingAs($this->ruben)->get("{$this->url}/team/sales")->assertForbidden();
    $this->actingAs($this->ruben)->post("{$this->url}/team/sales/{$this->lead->id}/fase", ['stage' => 'gebeld'])->assertForbidden();
    expect(ActivityLog::count())->toBe(0);
});

test('lead toevoegen en de uitnodiger zien bij een aanmelding via de link', function () {
    $code = Uitnodigingslink::voor($this->sanne)->code;
    $this->lead->update(['data' => ['referral_code' => $code]]);
    $this->actingAs($this->mirthe)->post("{$this->url}/team/sales", ['name' => 'Anna', 'company' => 'Het Dok', 'email' => 'anna@dok.test'])->assertSessionHasNoErrors();

    $leads = collect($this->actingAs($this->mirthe)->get("{$this->url}/team/sales")->viewData('page')['props']['leads']);
    expect($leads)->toHaveCount(2)
        ->and($leads->firstWhere('id', $this->lead->id)['invited_by'])->toBe('Sanne Hiemstra, Lunchroom Bloesem')
        ->and($leads->firstWhere('company', 'Het Dok')['source'])->toBe('Handmatig');
});
