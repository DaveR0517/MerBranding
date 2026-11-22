<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Melding aan het team: er is een nieuwe aanmelding binnen via de website. */
class NieuweLead extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        $soort = $this->lead->isOndernemer() ? 'Kennismaking ondernemer' : 'Aanmelding creator';

        return new Envelope(
            subject: "{$soort}: {$this->lead->name}".($this->lead->company ? " ({$this->lead->company})" : ''),
            replyTo: [new Address($this->lead->email, $this->lead->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.nieuwe-lead');
    }
}
