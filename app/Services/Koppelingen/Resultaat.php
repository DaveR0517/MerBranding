<?php

namespace App\Services\Koppelingen;

/** Uitkomst van een stap met een platform. Zonder sleutels altijd "niet ingesteld", nooit een uitzondering. */
final class Resultaat
{
    /** @param  array<string, mixed>  $data */
    private function __construct(public readonly string $status, public readonly string $melding, public readonly array $data = []) {}

    /** @param  array<string, mixed>  $data */
    public static function gelukt(array $data = [], string $melding = 'Gelukt'): self
    {
        return new self('gelukt', $melding, $data);
    }

    public static function nietIngesteld(string $platform): self
    {
        return new self('niet_ingesteld', "Koppelen met {$platform} is binnenkort beschikbaar. Tot dan vul je je account handmatig in.");
    }

    public static function fout(string $melding): self
    {
        return new self('fout', $melding);
    }

    public function isGelukt(): bool
    {
        return $this->status === 'gelukt';
    }
}
