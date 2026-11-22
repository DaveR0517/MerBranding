<?php

namespace App\Services\Koppelingen;

use App\Models\SocialAccount;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Response;

/**
 * Instagram API met Instagram Login (alleen professionele accounts). Token 60 dagen, te verlengen met ig_refresh_token.
 * Sinds 2025 heet "impressions" bij Meta "views". Cijfers kunnen tot 48 uur vertraagd zijn.
 * Documentatie: developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login
 */
class InstagramPlatform extends BasisPlatform
{
    private const MEDIA_VELDEN = 'id,caption,media_type,media_product_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count';

    private const PROFIEL_VELDEN = 'user_id,username,name,profile_picture_url,followers_count,follows_count,media_count';

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

    protected function standaardScopes(): array
    {
        return ['instagram_business_basic', 'instagram_business_manage_insights'];
    }

    protected function inlogAdres(): string
    {
        return 'https://www.instagram.com/oauth/authorize';
    }

    protected function inlogParameters(string $terugUrl, string $state, array $scopes): array
    {
        return [
            'client_id' => (string) config('services.instagram.client_id'),
            'redirect_uri' => $terugUrl,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
            'state' => $state,
        ];
    }

    private function graph(string $pad = ''): string
    {
        return 'https://graph.instagram.com/'.config('services.instagram.graph_version').($pad === '' ? '' : '/'.ltrim($pad, '/'));
    }

    /* ------------------------------------------------------------------ Inloggen en tokens */

    /** Code naar kort token (1 uur), dan naar lang token (60 dagen), dan de gebruikersnaam erbij. */
    public function wisselCodeIn(string $code, string $terugUrl): Resultaat
    {
        $kort = $this->roep('het inwisselen van de code', fn () => $this->http()->asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => config('services.instagram.client_id'), 'client_secret' => config('services.instagram.client_secret'),
            'grant_type' => 'authorization_code', 'redirect_uri' => $terugUrl, 'code' => preg_replace('/#_$/', '', $code),
        ]), function (array $j) {
            $d = $j['data'][0] ?? $j;
            $rechten = $d['permissions'] ?? [];

            return ['access_token' => $d['access_token'] ?? null, 'external_id' => isset($d['user_id']) ? (string) $d['user_id'] : null,
                'scopes' => array_values(array_filter(is_array($rechten) ? $rechten : explode(',', (string) $rechten)))];
        });
        if (! $kort->isGelukt() || ! $kort->data['access_token']) {
            return $kort->isGelukt() ? Resultaat::fout('Instagram gaf geen token terug.') : $kort;
        }

        $lang = $this->roep('het verlengen van het token', fn () => $this->http()->get('https://graph.instagram.com/access_token', [
            'grant_type' => 'ig_exchange_token', 'client_secret' => config('services.instagram.client_secret'), 'access_token' => $kort->data['access_token'],
        ]), fn (array $j) => $this->tokenData($j));
        if (! $lang->isGelukt()) {
            return $lang;
        }

        $account = new SocialAccount(['access_token' => $lang->data['access_token']]);
        $profiel = $this->profiel($account);

        return Resultaat::gelukt([
            'handle' => $profiel->isGelukt() ? $profiel->data['handle'] : null,
            'external_id' => $profiel->data['external_id'] ?? $kort->data['external_id'],
        ] + $lang->data + $kort->data);
    }

    /** Lang token verlengen (kan vanaf 24 uur na uitgifte, zolang het nog geldig is). */
    public function vernieuw(SocialAccount $account): Resultaat
    {
        return $this->roep('het verlengen van het token', fn () => $this->http()->get('https://graph.instagram.com/refresh_access_token', [
            'grant_type' => 'ig_refresh_token', 'access_token' => $account->access_token,
        ]), fn (array $j) => $this->tokenData($j));
    }

    /** @param  array<string, mixed>  $j */
    private function tokenData(array $j): array
    {
        $einde = now()->addSeconds((int) ($j['expires_in'] ?? $this->tokenDagen() * 86400));

        return ['access_token' => $j['access_token'] ?? null, 'expires_at' => $einde, 'token_expires_at' => $einde];
    }

    /** Webhooks aanzetten voor dit account (berichten en reacties). Nodig na elk koppelen. */
    public function abonneerWebhooks(SocialAccount $account): Resultaat
    {
        return $this->roep('het aanzetten van meldingen', fn () => $this->http()->withToken((string) $account->access_token)->asForm()
            ->post($this->graph('me/subscribed_apps'), ['subscribed_fields' => 'messages,comments']), fn (array $j) => ['gelukt' => (bool) ($j['success'] ?? false)]);
    }

    /* ------------------------------------------------------------------ Profiel, media en cijfers */

    public function profiel(SocialAccount $account): Resultaat
    {
        return $this->roep('het ophalen van het profiel', fn () => $this->http()->withToken((string) $account->access_token)
            ->get($this->graph('me'), ['fields' => self::PROFIEL_VELDEN]), fn (array $j) => [
                'external_id' => isset($j['user_id']) ? (string) $j['user_id'] : (isset($j['id']) ? (string) $j['id'] : null),
                'handle' => isset($j['username']) ? '@'.$j['username'] : null, 'naam' => $j['name'] ?? null, 'foto' => $j['profile_picture_url'] ?? null,
                'volgers' => $j['followers_count'] ?? null, 'volgend' => $j['follows_count'] ?? null, 'media' => $j['media_count'] ?? null, 'likes_totaal' => null,
            ]);
    }

    public function media(SocialAccount $account, int $paginas = 1): Resultaat
    {
        $items = [];
        $volgende = $this->graph('me/media');
        $query = ['fields' => self::MEDIA_VELDEN, 'limit' => 25];

        for ($i = 0; $i < $paginas && $volgende; $i++) {
            $pagina = $this->roep('het ophalen van de posts', fn () => $this->http()->withToken((string) $account->access_token)->get($volgende, $query), fn (array $j) => $j);
            if (! $pagina->isGelukt()) {
                return $i === 0 ? $pagina : Resultaat::gelukt(['items' => $items, 'cijfers_mee' => false]);
            }
            foreach ($pagina->data['data'] ?? [] as $m) {
                $items[] = $this->item((string) $m['id'], $m['media_type'] ?? null, $m['media_product_type'] ?? null, $m['caption'] ?? null,
                    $m['media_url'] ?? null, $m['thumbnail_url'] ?? ($m['media_url'] ?? null), $m['permalink'] ?? null, null, $m['timestamp'] ?? null,
                    ['likes' => $m['like_count'] ?? null, 'comments' => $m['comments_count'] ?? null]);
            }
            // De volgende-link bevat de zoekvragen al (een lege query zou die overschrijven).
            $volgende = $pagina->data['paging']['next'] ?? null;
            $query = null;
        }

        return Resultaat::gelukt(['items' => $items, 'cijfers_mee' => false]);
    }

    protected function cijfersVan(SocialAccount $account, array $item): Resultaat
    {
        $inzichten = $this->mediaInzichten($account, $item['extern_id'], $item['product']);

        return $inzichten->isGelukt() ? Resultaat::gelukt($inzichten->data + $item['cijfers']) : $inzichten;
    }

    /** Cijfers van één post. Stories hebben geen likes, reacties en opslagen. */
    public function mediaInzichten(SocialAccount $account, string $mediaId, ?string $product = null): Resultaat
    {
        $metrics = $product === 'STORY' ? 'views,reach,shares,total_interactions' : 'views,reach,likes,comments,shares,saved,total_interactions';

        return $this->roep('het ophalen van de cijfers van een post', fn () => $this->http()->withToken((string) $account->access_token)
            ->get($this->graph("{$mediaId}/insights"), ['metric' => $metrics]), fn (array $j) => $this->metriekWaarden($j));
    }

    /**
     * Accountcijfers van één dag: weergaven, bereik, betrokken accounts (total_value) en nieuwe volgers (alleen bij 100+ volgers).
     */
    public function accountInzichten(SocialAccount $account, CarbonInterface $dag): Resultaat
    {
        $periode = ['period' => 'day', 'since' => $dag->copy()->startOfDay()->timestamp, 'until' => $dag->copy()->addDay()->startOfDay()->timestamp];
        $totaal = $this->roep('het ophalen van de accountcijfers', fn () => $this->http()->withToken((string) $account->access_token)
            ->get($this->graph('me/insights'), ['metric' => 'reach,views,accounts_engaged', 'metric_type' => 'total_value'] + $periode), fn (array $j) => $this->metriekWaarden($j));
        if (! $totaal->isGelukt()) {
            return $totaal;
        }

        $volgers = $this->roep('het ophalen van nieuwe volgers', fn () => $this->http()->withToken((string) $account->access_token)
            ->get($this->graph('me/insights'), ['metric' => 'follower_count'] + $periode), fn (array $j) => $this->metriekWaarden($j));

        return Resultaat::gelukt([
            'weergaven' => $totaal->data['views'] ?? null, 'bereik' => $totaal->data['reach'] ?? null,
            'accounts_betrokken' => $totaal->data['accounts_engaged'] ?? null,
            'nieuwe_volgers' => $volgers->isGelukt() ? ($volgers->data['follower_count'] ?? null) : null,
        ]);
    }

    /**
     * Insights-antwoord naar naam => waarde. Meta geeft per metriek total_value of een reeks values.
     *
     * @param  array<string, mixed>  $j
     * @return array<string, int>
     */
    private function metriekWaarden(array $j): array
    {
        $uit = [];
        foreach ($j['data'] ?? [] as $m) {
            $waarde = $m['total_value']['value'] ?? collect($m['values'] ?? [])->sum('value');
            $uit[$m['name']] = (int) $waarde;
        }

        return $uit;
    }

    /* ------------------------------------------------------------------ Reacties */

    public function reacties(SocialAccount $account, string $mediaId): Resultaat
    {
        return $this->roep('het ophalen van reacties', fn () => $this->http()->withToken((string) $account->access_token)->get($this->graph("{$mediaId}/comments"), [
            'fields' => 'id,text,username,timestamp,like_count,replies{id,text,username,timestamp,like_count}', 'limit' => 50,
        ]), function (array $j) {
            $uit = [];
            foreach ($j['data'] ?? [] as $r) {
                $uit[] = $this->reactie($r, null);
                foreach ($r['replies']['data'] ?? [] as $a) {
                    $uit[] = $this->reactie($a, (string) $r['id']);
                }
            }

            return ['items' => $uit];
        });
    }

    /** @param  array<string, mixed>  $r */
    private function reactie(array $r, ?string $ouder): array
    {
        return ['extern_id' => (string) $r['id'], 'ouder_extern_id' => $ouder, 'gebruikersnaam' => $r['username'] ?? null, 'tekst' => $r['text'] ?? null,
            'likes' => (int) ($r['like_count'] ?? 0), 'geplaatst_op' => $r['timestamp'] ?? null];
    }

    public function beantwoordReactie(SocialAccount $account, string $reactieId, string $tekst): Resultaat
    {
        return $this->roep('het beantwoorden van een reactie', fn () => $this->http()->withToken((string) $account->access_token)->asForm()
            ->post($this->graph("{$reactieId}/replies"), ['message' => $tekst]), fn (array $j) => ['extern_id' => (string) ($j['id'] ?? '')]);
    }

    /* ------------------------------------------------------------------ Berichten (DM's) */

    /** Gesprekken met de laatste berichten (Meta geeft details van hooguit de laatste 20 berichten per gesprek). */
    public function gesprekken(SocialAccount $account, int $max = 20): Resultaat
    {
        $lijst = $this->roep('het ophalen van gesprekken', fn () => $this->http()->withToken((string) $account->access_token)->get($this->graph('me/conversations'), [
            'platform' => 'instagram', 'fields' => 'id,updated_time,participants', 'limit' => $max,
        ]), fn (array $j) => ['items' => $j['data'] ?? []]);
        if (! $lijst->isGelukt()) {
            return $lijst;
        }

        $uit = [];
        foreach ($lijst->data['items'] as $g) {
            $ander = collect($g['participants']['data'] ?? [])->first(fn ($p) => (string) $p['id'] !== (string) $account->external_id);
            $berichten = $this->roep('het ophalen van berichten', fn () => $this->http()->withToken((string) $account->access_token)->get($this->graph((string) $g['id']), [
                'fields' => 'messages.limit(20){id,created_time,from,to,message}',
            ]), fn (array $j) => ['items' => $j['messages']['data'] ?? []]);

            $uit[] = [
                'extern_id' => (string) $g['id'], 'deelnemer_id' => (string) ($ander['id'] ?? ''), 'deelnemer_naam' => $ander['username'] ?? null,
                'bijgewerkt' => $g['updated_time'] ?? null,
                'berichten' => collect($berichten->data['items'] ?? [])->map(fn ($b) => [
                    'extern_id' => (string) $b['id'], 'tekst' => $b['message'] ?? null, 'verzonden_op' => $b['created_time'] ?? null,
                    'richting' => (string) ($b['from']['id'] ?? '') === (string) $account->external_id ? 'uit' : 'in',
                ])->all(),
            ];
        }

        return Resultaat::gelukt(['items' => $uit]);
    }

    /** Antwoord sturen. Meta staat dat alleen toe binnen 24 uur na het laatste bericht van de ander. */
    public function stuurBericht(SocialAccount $account, string $ontvangerId, string $tekst): Resultaat
    {
        return $this->roep('het versturen van het bericht', fn () => $this->http()->withToken((string) $account->access_token)->post($this->graph('me/messages'), [
            'recipient' => ['id' => $ontvangerId], 'message' => ['text' => $tekst],
        ]), fn (array $j) => ['extern_id' => isset($j['message_id']) ? (string) $j['message_id'] : null]);
    }

    /* ------------------------------------------------------------------ Plaatsen (Content Publishing) */

    /**
     * Container maken: image_url (alleen JPEG), of video_url met media_type REELS, of een carrousel van kinderen.
     *
     * @param  array<string, mixed>  $velden
     */
    public function maakContainer(SocialAccount $account, array $velden): Resultaat
    {
        return $this->roep('het klaarzetten van de post', fn () => $this->http()->withToken((string) $account->access_token)->asForm()
            ->post($this->graph('me/media'), $velden), fn (array $j) => ['id' => (string) ($j['id'] ?? '')]);
    }

    /** Stand van een container: IN_PROGRESS, FINISHED, ERROR, EXPIRED of PUBLISHED. */
    public function containerStatus(SocialAccount $account, string $containerId): Resultaat
    {
        return $this->roep('het controleren van de post', fn () => $this->http()->withToken((string) $account->access_token)
            ->get($this->graph($containerId), ['fields' => 'status_code,status']), fn (array $j) => ['status' => $j['status_code'] ?? null, 'toelichting' => $j['status'] ?? null]);
    }

    public function publiceer(SocialAccount $account, string $containerId): Resultaat
    {
        $geplaatst = $this->roep('het plaatsen van de post', fn () => $this->http()->withToken((string) $account->access_token)->asForm()
            ->post($this->graph('me/media_publish'), ['creation_id' => $containerId]), fn (array $j) => ['id' => (string) ($j['id'] ?? '')]);
        if (! $geplaatst->isGelukt()) {
            return $geplaatst;
        }

        $link = $this->roep('het ophalen van de link', fn () => $this->http()->withToken((string) $account->access_token)
            ->get($this->graph($geplaatst->data['id']), ['fields' => 'permalink']), fn (array $j) => ['permalink' => $j['permalink'] ?? null]);

        return Resultaat::gelukt($geplaatst->data + ['permalink' => $link->data['permalink'] ?? null]);
    }

    /* ------------------------------------------------------------------ Fouten */

    protected function foutUit(Response $antwoord, array $json): ?string
    {
        if ($antwoord->successful() && ! isset($json['error']) && ! isset($json['error_message'])) {
            return null;
        }

        return (string) ($json['error']['message'] ?? $json['error_message'] ?? "HTTP {$antwoord->status()}");
    }

    protected function tokenOngeldig(Response $antwoord, array $json): bool
    {
        return (int) ($json['error']['code'] ?? $json['code'] ?? 0) === 190 || $antwoord->status() === 401;
    }
}
