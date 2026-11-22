<?php

namespace App\Mail;

use App\Models\Lead;
use App\Models\MessageTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * E-mail aan iemand die zich aanmeldde maar nog geen account heeft (aanvulling, afwijzing),
 * met de tekst uit het berichtsjabloon en de opmaak van de platformmeldingen.
 */
class ScreeningBericht extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param array<string, string> $vars */
    public function __construct(public string $sleutel, public Lead $lead, public array $vars, public string $url) {}

    private function sjabloon(): MessageTemplate
    {
        return MessageTemplate::where('key', $this->sleutel)->firstOrFail();
    }

    /** @return array<string, string> */
    private function variabelen(): array
    {
        return ['voornaam' => Str::before($this->lead->name, ' ')] + $this->vars;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: MessageTemplate::vulIn($this->sjabloon()->subject, $this->variabelen()));
    }

    public function content(): Content
    {
        $sjabloon = $this->sjabloon();
        $email = $sjabloon->email ?? [];
        $vars = $this->variabelen();

        return new Content(view: 'mail.melding', with: [
            'onderwerp' => MessageTemplate::vulIn($sjabloon->subject, $vars),
            'preheader' => MessageTemplate::vulIn($sjabloon->inapp, $vars),
            'aanhef' => MessageTemplate::vulIn($email['aanhef'] ?? 'Hoi {voornaam},', $vars),
            'alineas' => array_map(fn ($a) => MessageTemplate::vulIn($a, $vars), $email['alineas'] ?? []),
            'knopTekst' => $email['knop'] ?? 'Bekijken',
            'knopLink' => $this->url,
            'voettekst' => 'Je krijgt deze e-mail omdat je je aanmeldde als creator op mebranding.nl.',
        ]);
    }
}
