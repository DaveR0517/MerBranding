<?php

namespace App\Services\Gmail;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mail lezen en beantwoorden via de Gmail API. Niets wordt in de database bewaard: elke weergave haalt live op.
 */
class Mail
{
    public function __construct(private Workspace $workspace) {}

    /** Gesprekken in de inbox (of met een zoekopdracht), 25 per pagina. */
    public function gesprekken(string $postvak, ?string $zoek = null, ?string $pagina = null): array
    {
        $lijst = $this->workspace->http($postvak)->get(Workspace::API.'/threads', array_filter([
            'maxResults' => 25, 'labelIds' => $zoek ? null : 'INBOX', 'q' => $zoek, 'pageToken' => $pagina,
        ]))->throw()->json();

        $ids = array_column($lijst['threads'] ?? [], 'id');
        $token = $this->workspace->token($postvak);
        $antwoorden = Http::pool(fn (Pool $pool) => array_map(fn ($id) => $pool->as($id)->withToken($token)->acceptJson()->timeout(15)
            ->get(Workspace::API."/threads/{$id}", ['format' => 'metadata', 'metadataHeaders' => ['From', 'Subject', 'Date']]), $ids));

        $gesprekken = [];
        foreach ($ids as $id) {
            $t = $antwoorden[$id] instanceof Response && $antwoorden[$id]->successful() ? $antwoorden[$id]->json() : null;
            if (! $t) {
                continue;
            }
            $berichten = $t['messages'] ?? [];
            $laatste = end($berichten) ?: [];
            $eerste = $berichten[0] ?? [];
            $gesprekken[] = [
                'id' => $id,
                'onderwerp' => $this->kop($eerste, 'Subject') ?: '(geen onderwerp)',
                'van' => $this->naam($this->kop($laatste, 'From')),
                'datum' => isset($laatste['internalDate']) ? date(DATE_ATOM, (int) ($laatste['internalDate'] / 1000)) : null,
                'fragment' => html_entity_decode($laatste['snippet'] ?? '', ENT_QUOTES),
                'aantal' => count($berichten),
                'ongelezen' => collect($berichten)->contains(fn ($b) => in_array('UNREAD', $b['labelIds'] ?? [], true)),
            ];
        }

        return ['gesprekken' => $gesprekken, 'volgende' => $lijst['nextPageToken'] ?? null];
    }

    public function ongelezen(string $postvak): int
    {
        return (int) ($this->workspace->http($postvak)->get(Workspace::API.'/labels/INBOX')->json('threadsUnread') ?? 0);
    }

    /** Eén gesprek met alle berichten, en daarmee gelezen. */
    public function gesprek(string $postvak, string $id): array
    {
        $t = $this->workspace->http($postvak)->get(Workspace::API."/threads/{$id}", ['format' => 'full'])->throw()->json();
        $this->workspace->http($postvak)->post(Workspace::API."/threads/{$id}/modify", ['removeLabelIds' => ['UNREAD']]);

        $berichten = array_map(function ($b) {
            $delen = $this->delen($b['payload'] ?? []);

            return [
                'id' => $b['id'],
                'van' => $this->naam($this->kop($b, 'From')),
                'aan' => $this->kop($b, 'To'),
                'cc' => $this->kop($b, 'Cc'),
                'datum' => isset($b['internalDate']) ? date(DATE_ATOM, (int) ($b['internalDate'] / 1000)) : null,
                'html' => $delen['html'],
                'tekst' => $delen['tekst'] ?? ($delen['html'] ? null : html_entity_decode($b['snippet'] ?? '')),
                'bijlagen' => $delen['bijlagen'],
            ];
        }, $t['messages'] ?? []);

        return ['id' => $id, 'onderwerp' => $this->kop($t['messages'][0] ?? [], 'Subject') ?: '(geen onderwerp)', 'berichten' => $berichten];
    }

    public function archiveren(string $postvak, string $id): void
    {
        $this->workspace->http($postvak)->post(Workspace::API."/threads/{$id}/modify", ['removeLabelIds' => ['INBOX']])->throw();
    }

    public function bijlage(string $postvak, string $bericht, string $bijlage): string
    {
        $data = $this->workspace->http($postvak)->get(Workspace::API."/messages/{$bericht}/attachments/{$bijlage}")->throw()->json('data');

        return $this->b64($data ?? '');
    }

    /** Antwoord op het laatste bericht in het gesprek, verstuurd als dit postvak. */
    public function beantwoorden(string $postvak, string $id, string $tekst): void
    {
        $t = $this->workspace->http($postvak)->get(Workspace::API."/threads/{$id}", ['format' => 'metadata', 'metadataHeaders' => ['From', 'Reply-To', 'To', 'Subject', 'Message-ID', 'References']])->throw()->json();
        $berichten = $t['messages'] ?? [];
        // Antwoord naar de laatste afzender die niet dit postvak zelf is.
        $op = collect($berichten)->reverse()->first(fn ($b) => ! str_contains(strtolower($this->kop($b, 'From')), $postvak)) ?? end($berichten);
        if (! $op) {
            throw new RuntimeException('Gesprek niet gevonden.');
        }
        $aan = $this->kop($op, 'Reply-To') ?: $this->kop($op, 'From');
        $onderwerp = $this->kop($berichten[0], 'Subject');
        $onderwerp = Str::startsWith(Str::lower($onderwerp), 're:') ? $onderwerp : 'Re: '.$onderwerp;
        $messageId = $this->kop($op, 'Message-ID');
        $referenties = trim($this->kop($op, 'References').' '.$messageId);

        $mime = implode("\r\n", array_filter([
            'From: '.$postvak,
            'To: '.$aan,
            'Subject: =?UTF-8?B?'.base64_encode($onderwerp).'?=',
            $messageId ? 'In-Reply-To: '.$messageId : null,
            $referenties ? 'References: '.$referenties : null,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ]))."\r\n\r\n".chunk_split(base64_encode($tekst));

        $this->workspace->http($postvak)->post(Workspace::API.'/messages/send', [
            'raw' => rtrim(strtr(base64_encode($mime), '+/', '-_'), '='), 'threadId' => $id,
        ])->throw();
    }

    private function kop(array $bericht, string $naam): string
    {
        foreach ($bericht['payload']['headers'] ?? [] as $h) {
            if (strcasecmp($h['name'], $naam) === 0) {
                return (string) $h['value'];
            }
        }

        return '';
    }

    /** "Naam <adres>" naar ['naam' => ..., 'email' => ...]. */
    private function naam(string $van): array
    {
        if (preg_match('/^\s*"?([^"<]*?)"?\s*<([^>]+)>/', $van, $m)) {
            return ['naam' => trim($m[1]) ?: $m[2], 'email' => $m[2]];
        }

        return ['naam' => trim($van), 'email' => trim($van)];
    }

    private function b64(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }

    /** @return array{html: ?string, tekst: ?string, bijlagen: list<array{naam: string, grootte: int, id: string, type: string}>} */
    private function delen(array $deel, array $uit = ['html' => null, 'tekst' => null, 'bijlagen' => []]): array
    {
        $type = strtolower($deel['mimeType'] ?? '');
        if (! empty($deel['filename']) && isset($deel['body']['attachmentId'])) {
            $uit['bijlagen'][] = ['naam' => $deel['filename'], 'grootte' => (int) ($deel['body']['size'] ?? 0), 'id' => $deel['body']['attachmentId'], 'type' => $type];
        } elseif ($type === 'text/html' && $uit['html'] === null && isset($deel['body']['data'])) {
            $uit['html'] = $this->b64($deel['body']['data']);
        } elseif ($type === 'text/plain' && $uit['tekst'] === null && isset($deel['body']['data'])) {
            $uit['tekst'] = $this->b64($deel['body']['data']);
        }
        foreach (Arr::wrap($deel['parts'] ?? []) as $sub) {
            $uit = $this->delen($sub, $uit);
        }

        return $uit;
    }
}
