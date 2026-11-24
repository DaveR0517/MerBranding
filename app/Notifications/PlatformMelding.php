<?php

namespace App\Notifications;

use App\Models\MessageTemplate;
use App\Models\User;
use App\Notifications\Kanalen\WebpushKanaal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Eén melding uit de berichtsjablonen (database/data/berichtsjablonen.json): in de app en, als het sjabloon dat zegt,
 * per e-mail en als pushmelding op de apparaten waar de gebruiker meldingen aanzette (webapp).
 */
class PlatformMelding extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, string> $vars */
    public function __construct(public MessageTemplate $template, public array $vars, public string $url) {}

    public function via(object $notifiable): array
    {
        return array_values(array_filter([
            'database',
            $this->template->wants('email') ? 'mail' : null,
            $this->template->wants('push') && $notifiable instanceof User && $notifiable->pushAbonnementen()->exists() ? WebpushKanaal::class : null,
        ]));
    }

    /** @return array{title: string, body: string, url: string} */
    public function toWebpush(object $notifiable): array
    {
        return [
            'title' => MessageTemplate::vulIn($this->template->subject, $this->vars),
            'body' => MessageTemplate::vulIn($this->template->push ?: $this->template->inapp, $this->vars),
            'url' => $this->url,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'key' => $this->template->key,
            'title' => MessageTemplate::vulIn($this->template->subject, $this->vars),
            'body' => MessageTemplate::vulIn($this->template->inapp, $this->vars),
            'url' => $this->url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $this->template->email ?? [];
        $onderwerp = MessageTemplate::vulIn($this->template->subject, $this->vars);

        return (new MailMessage)
            ->subject($onderwerp)
            ->view('mail.melding', [
                'onderwerp' => $onderwerp,
                'preheader' => MessageTemplate::vulIn($this->template->inapp, $this->vars),
                'aanhef' => MessageTemplate::vulIn($email['aanhef'] ?? 'Hoi {voornaam},', $this->vars),
                'alineas' => array_map(fn ($a) => MessageTemplate::vulIn($a, $this->vars), $email['alineas'] ?? []),
                'knopTekst' => $email['knop'] ?? 'Bekijken',
                'knopLink' => $this->url,
                'voettekst' => 'Je krijgt deze e-mail omdat je een account hebt bij MEBRANDING.',
            ]);
    }
}
