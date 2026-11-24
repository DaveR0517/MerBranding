<?php

namespace App\Services\Gmail;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Toegang tot Gmail namens een postvak in de Workspace van MEBRANDING: service-account met domeinbrede delegatie.
 * Per postvak een eigen toegangstoken (JWT met sub = postvak), 55 minuten bewaard in de cache.
 */
class Workspace
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const API = 'https://gmail.googleapis.com/gmail/v1/users/me';

    public function ingesteld(): bool
    {
        return $this->sleutel() !== null;
    }

    /** @return array{client_email: string, private_key: string}|null */
    private function sleutel(): ?array
    {
        $ruw = config('gmail.service_account');
        if (! $ruw) {
            return null;
        }
        $json = json_decode(str_starts_with(trim($ruw), '{') ? $ruw : (string) base64_decode($ruw, true), true);

        return isset($json['client_email'], $json['private_key']) ? $json : null;
    }

    public function http(string $postvak): PendingRequest
    {
        return Http::withToken($this->token($postvak))->acceptJson()->timeout(15)->retry(2, 300, throw: false);
    }

    public function token(string $postvak): string
    {
        return Cache::remember('gmail-token:'.md5($postvak), now()->addMinutes(55), function () use ($postvak) {
            $sleutel = $this->sleutel() ?? throw new RuntimeException('Gmail is nog niet ingesteld (GMAIL_SERVICE_ACCOUNT).');
            $nu = time();
            $deel = fn (array $d) => rtrim(strtr(base64_encode(json_encode($d)), '+/', '-_'), '=');
            $kop = $deel(['alg' => 'RS256', 'typ' => 'JWT']);
            $inhoud = $deel(['iss' => $sleutel['client_email'], 'sub' => $postvak, 'scope' => implode(' ', config('gmail.scopes')), 'aud' => self::TOKEN_URL, 'iat' => $nu, 'exp' => $nu + 3600]);
            openssl_sign("{$kop}.{$inhoud}", $handtekening, $sleutel['private_key'], 'sha256WithRSAEncryption');
            $jwt = "{$kop}.{$inhoud}.".rtrim(strtr(base64_encode($handtekening), '+/', '-_'), '=');

            $antwoord = Http::asForm()->timeout(15)->post(self::TOKEN_URL, ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]);
            if (! $antwoord->successful()) {
                throw new RuntimeException('Google gaf geen toegang tot '.$postvak.': '.($antwoord->json('error_description') ?? $antwoord->json('error') ?? $antwoord->status()));
            }

            return $antwoord->json('access_token');
        });
    }
}
