<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Bevestiging aan de aanvrager, in de huisstijl. */
class LeadBevestiging extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->lead->isOndernemer()
                ? 'We hebben je aanvraag ontvangen'
                : 'Je aanmelding als creator is binnen',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.lead-bevestiging');
    }
}
