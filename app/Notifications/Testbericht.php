<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Testmail vanuit Berichtsjablonen: dezelfde e-mail als PlatformMelding, alleen per e-mail, met de voorbeeldwaarden.
 * Wordt direct verstuurd (notifyNow), dus ook een sjabloon dat nog niet is opgeslagen kan worden getest.
 */
class Testbericht extends PlatformMelding
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = parent::toMail($notifiable);

        return $mail->subject('[Test] '.$mail->subject);
    }
}
