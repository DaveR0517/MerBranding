<?php

namespace App\Services\Kanalen;

use App\Models\KanaalGesprek;
use App\Models\KanaalMedia;
use App\Models\KanaalReactie;
use App\Models\SocialAccount;
use App\Services\Koppelingen\Koppelingen;
use App\Services\Melder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Binnenkomende meldingen van Meta en TikTok. Eerst de handtekening controleren, dan verwerken.
 * Meta: X-Hub-Signature-256 = sha256=HMAC(body, app secret). TikTok: TikTok-Signature = t=...,s=HMAC("t.body", client secret).
 */
class Webhooks
{
    public function __construct(private EigenKanalen $kanalen, private Koppelingen $koppelingen, private Melder $melder) {}

    public function metaHandtekeningKlopt(string $body, ?string $kop): bool
    {
        $geheim = (string) config('services.instagram.client_secret');
        if ($geheim === '' || ! $kop || ! str_starts_with($kop, 'sha256=')) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $geheim), substr($kop, 7));
    }

    public function tiktokHandtekeningKlopt(string $body, ?string $kop): bool
    {
        $geheim = (string) config('services.tiktok.client_secret');
        if ($geheim === '' || ! $kop) {
            return false;
        }
        parse_str(str_replace(',', '&', $kop), $delen);
        $t = (string) ($delen['t'] ?? '');
        $s = (string) ($delen['s'] ?? '');
        // Oude meldingen (meer dan 5 minuten) weigeren tegen herhaalaanvallen.
        if ($t === '' || $s === '' || abs(time() - (int) $t) > 300) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', "{$t}.{$body}", $geheim), $s);
    }

    /**
     * Instagram: berichten (messaging) en reacties (changes, veld comments).
     *
     * @param  array<string, mixed>  $payload
     * @return int aantal verwerkte gebeurtenissen
     */
    public function instagram(array $payload): int
    {
        $aantal = 0;
        foreach ($payload['entry'] ?? [] as $entry) {
            $account = SocialAccount::eigen()->where('platform', 'instagram')->where('external_id', (string) ($entry['id'] ?? ''))->first();
            if (! $account) {
                continue;
            }

            foreach ($entry['messaging'] ?? [] as $m) {
                $bericht = $m['message'] ?? null;
                if (! $bericht || ($bericht['is_deleted'] ?? false)) {
                    continue;
                }
                $uit = (bool) ($bericht['is_echo'] ?? false);
                $ander = (string) ($uit ? ($m['recipient']['id'] ?? '') : ($m['sender']['id'] ?? ''));
                $op = isset($m['timestamp']) ? Carbon::createFromTimestampMs((int) $m['timestamp']) : now();
                $bijlagen = isset($bericht['attachments']) ? collect($bericht['attachments'])->map(fn ($b) => ['soort' => $b['type'] ?? null, 'url' => $b['payload']['url'] ?? null])->all() : null;
                if ($ander !== '' && $this->kanalen->bewaarBericht($account, $ander, null, $bericht['mid'] ?? null, $uit ? 'uit' : 'in', $bericht['text'] ?? null, $op, null, ! $uit, null, $bijlagen)) {
                    $aantal++;
                }
            }

            foreach ($entry['changes'] ?? [] as $c) {
                if (($c['field'] ?? null) === 'comments' && $this->reactie($account, $c['value'] ?? [])) {
                    $aantal++;
                }
            }
        }

        return $aantal;
    }

    /** @param  array<string, mixed>  $v */
    private function reactie(SocialAccount $account, array $v): bool
    {
        if (! isset($v['id'], $v['media']['id'])) {
            return false;
        }
        $media = KanaalMedia::firstOrCreate(['social_account_id' => $account->id, 'extern_id' => (string) $v['media']['id']], ['product' => $v['media']['media_product_type'] ?? null]);
        $eigen = (string) ($v['from']['id'] ?? '') === (string) $account->external_id;
        $reactie = KanaalReactie::firstOrCreate(['extern_id' => (string) $v['id']], [
            'kanaal_media_id' => $media->id, 'ouder_extern_id' => $v['parent_id'] ?? null, 'gebruikersnaam' => $v['from']['username'] ?? null,
            'tekst' => $v['text'] ?? null, 'eigen' => $eigen, 'geplaatst_op' => now(),
        ]);

        if ($reactie->wasRecentlyCreated && ! $eigen) {
            $this->melder->stuur('t-kanaal-reactie', $this->melder->team('kanalen.beheren'),
                ['afzender' => isset($v['from']['username']) ? '@'.$v['from']['username'] : 'iemand', 'tekst' => Str::limit((string) ($v['text'] ?? ''), 80)],
                route('team.kanalen.posts', ['post' => $media->id]));
        }

        return $reactie->wasRecentlyCreated;
    }

    /**
     * TikTok: toegang ingetrokken (authorization.removed) zet de koppeling uit. Andere meldingen alleen in het logboek.
     *
     * @param  array<string, mixed>  $payload
     */
    public function tiktok(array $payload): bool
    {
        $account = SocialAccount::withoutGlobalScope('klanten')->where('platform', 'tiktok')->where('external_id', (string) ($payload['user_openid'] ?? ''))->first();
        if (! $account) {
            return false;
        }

        if (($payload['event'] ?? null) === 'authorization.removed') {
            $this->trekIn($account, 'tiktok_toegang_ingetrokken');
        } else {
            $this->koppelingen->log($account, null, 'tiktok_melding', $account->status, ['event' => $payload['event'] ?? null]);
        }

        return true;
    }

    /**
     * Ondertekend verzoek van Meta (ontkoppelen of gegevens verwijderen): payload of null als de handtekening niet klopt.
     *
     * @return array<string, mixed>|null
     */
    public function metaSignedRequest(?string $signed): ?array
    {
        $geheim = (string) config('services.instagram.client_secret');
        if (! $signed || $geheim === '' || ! str_contains($signed, '.')) {
            return null;
        }
        [$sig, $data] = explode('.', $signed, 2);
        $b64 = fn (string $s) => base64_decode(strtr($s, '-_', '+/'));
        if (! hash_equals(hash_hmac('sha256', $data, $geheim, true), (string) $b64($sig))) {
            return null;
        }

        return json_decode((string) $b64($data), true) ?: null;
    }

    /**
     * Iemand haalde de app weg bij Instagram: koppeling uit, tokens weg. Bij een verwijderverzoek ook de opgehaalde gegevens van het eigen kanaal.
     */
    public function instagramIngetrokken(string $gebruikerId, bool $verwijderen): void
    {
        $accounts = SocialAccount::withoutGlobalScope('klanten')->where('platform', 'instagram')->where('external_id', $gebruikerId)->get();
        foreach ($accounts as $account) {
            $this->trekIn($account, $verwijderen ? 'gegevens_verwijderd' : 'instagram_toegang_ingetrokken');
            if ($verwijderen && $account->owner_type === SocialAccount::EIGEN) {
                KanaalMedia::where('social_account_id', $account->id)->delete();
                KanaalGesprek::where('social_account_id', $account->id)->delete();
            }
        }
    }

    private function trekIn(SocialAccount $account, string $event): void
    {
        $van = $account->status;
        $account->forceFill(['status' => 'niet_gekoppeld', 'access_token' => null, 'refresh_token' => null, 'expires_at' => null, 'token_expires_at' => null, 'scopes' => null])->save();
        $this->koppelingen->log($account, null, $event, $van);
    }
}
