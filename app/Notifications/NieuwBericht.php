<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Melding in de app bij een nieuw bericht of een antwoord op een supportvraag.
 * Er is (nog) geen sjabloon in de berichtsjablonen voor deze gebeurtenissen; daarom een eigen melding, alleen in de app.
 */
class NieuwBericht extends Notification
{
    public function __construct(public string $titel, public string $tekst, public string $url, public string $key = 'nieuw-bericht') {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['key' => $this->key, 'title' => $this->titel, 'body' => $this->tekst, 'url' => $this->url];
    }
}
