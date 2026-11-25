<?php

namespace App\Services\Gmail;

use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Mailer "gmail": het portaal verstuurt mail via de Gmail API (HTTPS) namens een postvak in de Workspace.
 * Nodig omdat de server geen uitgaande mailpoorten (25, 465, 587) mag gebruiken.
 * Afzender: het From-adres als dat een postvak van GMAIL_POSTVAKKEN is, anders MAIL_FROM_ADDRESS.
 */
class GmailTransport extends AbstractTransport
{
    public function __construct(private Workspace $workspace)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $postvak = $this->postvak($message);
        $ruw = $message->toString();

        // Gmail bezorgt alleen aan adressen in de kop; Bcc staat daar niet meer in, dus terugzetten.
        $origineel = $message->getOriginalMessage();
        if ($origineel instanceof Email) {
            $zichtbaar = collect([...$origineel->getTo(), ...$origineel->getCc()])->map(fn (Address $a) => strtolower($a->getAddress()));
            $verborgen = collect($message->getEnvelope()->getRecipients())->map(fn (Address $a) => $a->getAddress())
                ->reject(fn (string $a) => $zichtbaar->contains(strtolower($a)));
            if ($verborgen->isNotEmpty()) {
                $ruw = 'Bcc: '.$verborgen->implode(', ')."\r\n".$ruw;
            }
        }

        $antwoord = $this->workspace->http($postvak)->post(Workspace::API.'/messages/send', [
            'raw' => rtrim(strtr(base64_encode($ruw), '+/', '-_'), '='),
        ]);
        if (! $antwoord->successful()) {
            throw new RuntimeException('Gmail weigerde de mail vanuit '.$postvak.': '.($antwoord->json('error.message') ?? $antwoord->status()));
        }
    }

    private function postvak(SentMessage $message): string
    {
        $van = strtolower($message->getEnvelope()->getSender()->getAddress());

        return array_key_exists($van, config('gmail.postvakken')) ? $van : strtolower((string) config('mail.from.address'));
    }

    public function __toString(): string
    {
        return 'gmail';
    }
}
