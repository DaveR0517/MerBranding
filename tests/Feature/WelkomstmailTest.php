<?php

use App\Models\Lead;
use App\Models\User;
use App\Services\Screening;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/* De mail na goedkeuren: van support@mebranding.nl, met inloggegevens (e-mailadres en link om het wachtwoord te kiezen), nooit een wachtwoord. */
uses(RefreshDatabase::class);

test('welkomstmail na goedkeuren: afzender, inlog-e-mailadres, persoonlijke link en geen wachtwoord', function () {
    config(['mail.default' => 'array', 'mail.from.address' => 'support@mebranding.nl', 'mail.from.name' => 'MEBRANDING']);
    $this->seed(DemoSeeder::class);
    $lead = Lead::create(['type' => Lead::TYPE_CREATOR, 'name' => 'Eline Vera van der Linde', 'email' => 'eline@voorbeeld.test', 'city' => 'Leeuwarden', 'data' => ['soort' => 'ugc']]);

    app(Screening::class)->goedkeuren($lead->fresh(), User::where('email', 'ruben@demo.mebranding.nl')->sole(), 'bronze', 'Mooi werk');

    $berichten = app('mailer')->getSymfonyTransport()->messages();
    $mail = collect($berichten)->map->getOriginalMessage()->first(fn ($m) => $m->getTo()[0]->getAddress() === 'eline@voorbeeld.test');
    expect($mail)->not->toBeNull();

    $html = $mail->getHtmlBody();
    expect($mail->getFrom()[0]->getAddress())->toBe('support@mebranding.nl')
        ->and($mail->getSubject())->toBe('Je account bij MEBRANDING staat klaar')
        ->and($html)->toContain('eline@voorbeeld.test')
        ->and($html)->toContain('reset-password')
        ->and($html)->toContain('Wachtwoord kiezen en inloggen')
        ->and($html)->toContain('Bronze')
        ->and($html)->not->toMatch('/wachtwoord:\s*\S{6,}/i'); // geen wachtwoord in de mail
});
