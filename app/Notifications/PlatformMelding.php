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
 * Eén melding uit de berichtsjablonen (database/data/berichtsjablonen.json). Kanaalregel (besluit Dave 25-11-2026):
 * vaste zaken (bevestigingen, account, geld, juridisch) per mail; tijdgevoelige zaken als push, met mail als vangnet
 * voor wie geen pushmeldingen heeft; alles staat ook in het portaal. Teamsignalen met "samenvatting" gaan niet los per
 * mail maar komen in het dagoverzicht (t-dagoverzicht).
 */
class PlatformMelding extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, string> $vars */
    public function __construct(public MessageTemplate $template, public array $vars, public string $url) {}

    public function via(object $notifiable): array
    {
        $push = $this->template->wants('push') && $notifiable instanceof User && $notifiable->pushAbonnementen()->exists();

        return array_values(array_filter([
            ($this->template->channels['inapp'] ?? 'aan') !== 'nee' ? 'database' : null,
            $this->template->wants('email') || ($this->template->vangnet() && ! $push) ? 'mail' : null,
            $push ? WebpushKanaal::class : null,
        ]));
    }

    /** @return array{title: string, body: string, url: string} */
    public function toWebpush(object $notifiable): array
    {
        return [
            'title' => $this->vul($this->template->subject),
            'body' => $this->vul($this->template->push ?: $this->template->inapp),
            'url' => $this->url,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'key' => $this->template->key,
            'title' => $this->vul($this->template->subject),
            'body' => $this->vul($this->template->inapp),
            'url' => $this->url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $this->template->email ?? [];
        $onderwerp = $this->vul($this->template->subject);

        return (new MailMessage)
            ->subject($onderwerp)
            ->view('mail.melding', [
                'vorm' => $this->template->vorm ?? 'standaard',
                'onderwerp' => $onderwerp,
                'preheader' => $this->vul($this->template->inapp ?: ($email['alineas'][0] ?? '')),
                'aanhef' => $this->vul($email['aanhef'] ?? 'Hoi {voornaam},'),
                'alineas' => array_map(fn ($a) => $this->vul($a), $email['alineas'] ?? []),
                'bedrag' => isset($email['bedrag']) ? $this->vul($email['bedrag']) : null,
                'gegevens' => array_map(fn ($g) => [$g[0], $this->vul($g[1])], $email['gegevens'] ?? []),
                'stappen' => array_map(fn ($a) => $this->vul($a), $email['stappen'] ?? []),
                'regels' => $this->regels(),
                'naschrift' => isset($email['naschrift']) ? $this->vul($email['naschrift']) : null,
                'knopTekst' => $email['knop'] ?? 'Bekijken',
                'knopLink' => $this->url,
                'voettekst' => 'Je krijgt deze e-mail omdat je een account hebt bij MEBRANDING.',
            ]);
    }

    /** Lijst voor de samenvatting: regels in de vorm [titel, tekst, link], meegegeven als vars['regels'] (JSON). */
    protected function regels(): array
    {
        return json_decode($this->vars['regels'] ?? '[]', true) ?: [];
    }

    private function vul(?string $tekst): string
    {
        return MessageTemplate::vulIn($tekst, $this->vars);
    }
}
