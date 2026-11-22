<?php

namespace App\Services\Koppelingen;

/** TikTok Display API: views, likes, reacties en shares van openbare video's. Omslag-URL verloopt na 6 uur. */
class TikTokPlatform extends BasisPlatform
{
    public function sleutel(): string
    {
        return 'tiktok';
    }

    public function naam(): string
    {
        return 'TikTok';
    }

    /** Het toegangstoken geldt een dag, het vernieuwtoken een jaar: de koppeling loopt af met het vernieuwtoken. */
    public function tokenDagen(): int
    {
        return 365;
    }

    protected function inlogAdres(): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize/';
    }

    protected function inlogParameters(string $terugUrl, string $state): array
    {
        return [
            'client_key' => (string) config('services.tiktok.client_id'),
            'redirect_uri' => $terugUrl,
            'response_type' => 'code',
            'scope' => 'user.info.basic,video.list',
            'state' => $state,
        ];
    }
}
