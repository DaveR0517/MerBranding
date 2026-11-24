<?php

namespace App\Notifications\Kanalen;

use App\Models\User;
use App\Services\Webpush;
use Illuminate\Notifications\Notification;

/** Kanaal 'push' van de berichtsjablonen: een melding op de telefoon of computer via de webapp. */
class WebpushKanaal
{
    public function __construct(private Webpush $webpush) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toWebpush')) {
            return;
        }
        $bericht = $notification->toWebpush($notifiable);
        $this->webpush->stuur($notifiable, $bericht['title'], $bericht['body'], $bericht['url']);
    }
}
