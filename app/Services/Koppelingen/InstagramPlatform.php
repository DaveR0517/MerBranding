<?php

namespace App\Services\Koppelingen;

/** Instagram Login (alleen professionele accounts), tokens 60 dagen, metric views, cijfers tot 48 uur vertraagd. */
class InstagramPlatform extends BasisPlatform
{
    public function sleutel(): string
    {
        return 'instagram';
    }

    public function naam(): string
    {
        return 'Instagram';
    }

    public function tokenDagen(): int
    {
        return 60;
    }

    protected function inlogAdres(): string
    {
        return 'https://www.instagram.com/oauth/authorize';
    }

    protected function inlogParameters(string $terugUrl, string $state): array
    {
        return [
            'client_id' => (string) config('services.instagram.client_id'),
            'redirect_uri' => $terugUrl,
            'response_type' => 'code',
            'scope' => 'instagram_business_basic,instagram_business_manage_insights',
            'state' => $state,
        ];
    }
}
