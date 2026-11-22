<?php

namespace App\Services\Koppelingen;

use App\Models\SocialAccount;
use Illuminate\Http\Client\Response;

/**
 * TikTok Login Kit en Display API: profiel en openbare video's met views, likes, reacties en shares.
 * Toegangstoken 24 uur, vernieuwtoken 365 dagen. Omslag-URL's verlopen na ongeveer 6 uur.
 * TikTok heeft geen API voor privéberichten. Documentatie: developers.tiktok.com/doc/display-api-overview
 */
class TikTokPlatform extends BasisPlatform
{
    private const API = 'https://open.tiktokapis.com/v2';

    private const VIDEO_VELDEN = 'id,title,video_description,cover_image_url,share_url,create_time,duration,view_count,like_count,comment_count,share_count';

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

    protected function standaardScopes(): array
    {
        return ['user.info.basic', 'video.list'];
    }

    protected function inlogAdres(): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize/';
    }

    protected function inlogParameters(string $terugUrl, string $state, array $scopes): array
    {
        return [
            'client_key' => (string) config('services.tiktok.client_id'),
            'redirect_uri' => $terugUrl,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
            'state' => $state,
        ];
    }

    /* ------------------------------------------------------------------ Tokens */

    public function wisselCodeIn(string $code, string $terugUrl): Resultaat
    {
        $tokens = $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $terugUrl]);
        if (! $tokens->isGelukt()) {
            return $tokens;
        }

        $profiel = $this->profiel(new SocialAccount(['access_token' => $tokens->data['access_token']]));

        return Resultaat::gelukt($tokens->data + ['handle' => $profiel->isGelukt() ? $profiel->data['handle'] : null]);
    }

    /** Elke dag nodig: het toegangstoken verloopt na 24 uur. TikTok kan een nieuw vernieuwtoken meegeven. */
    public function vernieuw(SocialAccount $account): Resultaat
    {
        return $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token]);
    }

    /** @param  array<string, mixed>  $velden */
    private function token(array $velden): Resultaat
    {
        return $this->roep('het ophalen van het token', fn () => $this->http()->asForm()->post(self::API.'/oauth/token/', [
            'client_key' => config('services.tiktok.client_id'), 'client_secret' => config('services.tiktok.client_secret'),
        ] + $velden), fn (array $j) => [
            'access_token' => $j['access_token'] ?? null, 'refresh_token' => $j['refresh_token'] ?? null, 'external_id' => $j['open_id'] ?? null,
            'token_expires_at' => now()->addSeconds((int) ($j['expires_in'] ?? 86400)),
            'expires_at' => now()->addSeconds((int) ($j['refresh_expires_in'] ?? $this->tokenDagen() * 86400)),
            'scopes' => array_values(array_filter(explode(',', (string) ($j['scope'] ?? '')))),
        ]);
    }

    /** Toegang intrekken bij ontkoppelen. */
    public function intrekken(SocialAccount $account): Resultaat
    {
        return $this->roep('het intrekken van de toegang', fn () => $this->http()->asForm()->post(self::API.'/oauth/revoke/', [
            'client_key' => config('services.tiktok.client_id'), 'client_secret' => config('services.tiktok.client_secret'), 'token' => $account->access_token,
        ]), fn (array $j) => []);
    }

    /* ------------------------------------------------------------------ Profiel en video's */

    public function profiel(SocialAccount $account): Resultaat
    {
        return $this->roep('het ophalen van het profiel', fn () => $this->http()->withToken((string) $account->access_token)->get(self::API.'/user/info/', [
            'fields' => 'open_id,username,display_name,avatar_url,follower_count,following_count,likes_count,video_count',
        ]), function (array $j) {
            $u = $j['data']['user'] ?? [];

            return [
                'external_id' => $u['open_id'] ?? null, 'handle' => isset($u['username']) ? '@'.$u['username'] : null,
                'naam' => $u['display_name'] ?? null, 'foto' => $u['avatar_url'] ?? null, 'volgers' => $u['follower_count'] ?? null,
                'volgend' => $u['following_count'] ?? null, 'media' => $u['video_count'] ?? null, 'likes_totaal' => $u['likes_count'] ?? null,
            ];
        });
    }

    public function media(SocialAccount $account, int $paginas = 1): Resultaat
    {
        $items = [];
        $cursor = null;

        for ($i = 0; $i < $paginas; $i++) {
            $pagina = $this->roep('het ophalen van de video\'s', fn () => $this->http()->withToken((string) $account->access_token)
                ->post(self::API.'/video/list/?fields='.self::VIDEO_VELDEN, array_filter(['max_count' => 20, 'cursor' => $cursor])), fn (array $j) => $j['data'] ?? []);
            if (! $pagina->isGelukt()) {
                return $i === 0 ? $pagina : Resultaat::gelukt(['items' => $items, 'cijfers_mee' => true]);
            }
            foreach ($pagina->data['videos'] ?? [] as $v) {
                $items[] = $this->item((string) $v['id'], 'VIDEO', null, $v['title'] ?? ($v['video_description'] ?? null), null, $v['cover_image_url'] ?? null,
                    $v['share_url'] ?? null, isset($v['duration']) ? (int) $v['duration'] : null,
                    isset($v['create_time']) ? date(DATE_ATOM, (int) $v['create_time']) : null,
                    ['views' => $v['view_count'] ?? null, 'likes' => $v['like_count'] ?? null, 'comments' => $v['comment_count'] ?? null, 'shares' => $v['share_count'] ?? null]);
            }
            if (! ($pagina->data['has_more'] ?? false)) {
                break;
            }
            $cursor = $pagina->data['cursor'] ?? null;
        }

        return Resultaat::gelukt(['items' => $items, 'cijfers_mee' => true]);
    }

    /* ------------------------------------------------------------------ Fouten */

    protected function foutUit(Response $antwoord, array $json): ?string
    {
        $fout = $json['error'] ?? null;
        $ok = ! $fout || (is_array($fout) && ($fout['code'] ?? 'ok') === 'ok');
        if ($antwoord->successful() && $ok) {
            return null;
        }

        return (string) (is_array($fout) ? ($fout['message'] ?? $fout['code'] ?? '') : ($json['error_description'] ?? $fout)) ?: "HTTP {$antwoord->status()}";
    }

    protected function tokenOngeldig(Response $antwoord, array $json): bool
    {
        $code = is_array($json['error'] ?? null) ? ($json['error']['code'] ?? '') : ($json['error'] ?? '');

        return in_array($code, ['access_token_invalid', 'invalid_grant', 'scope_not_authorized'], true) || $antwoord->status() === 401;
    }
}
