<?php

namespace App\Services\Kanalen;

use App\Models\KanaalBericht;
use App\Models\KanaalGesprek;
use App\Models\KanaalMedia;
use App\Models\KanaalMeting;
use App\Models\KanaalReactie;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Koppelingen\InstagramPlatform;
use App\Services\Koppelingen\Koppelingen;
use App\Services\Koppelingen\Platform;
use App\Services\Koppelingen\Resultaat;
use App\Services\Koppelingen\TikTokPlatform;
use App\Services\Melder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Eigen kanalen van MEBRANDING: koppelen, verversen (profiel, posts, cijfers, gesprekken), tokens verlengen.
 * De API-aanroepen zelf staan in de platformklassen (App\Services\Koppelingen), zodat creatorkoppelingen ze ook gebruiken.
 */
class EigenKanalen
{
    public function __construct(private Koppelingen $koppelingen, private Melder $melder) {}

    public function platform(string $sleutel): Platform
    {
        return $this->koppelingen->platform($sleutel);
    }

    /** @return list<string> */
    public function sleutels(): array
    {
        return $this->koppelingen->sleutels();
    }

    public function account(string $platform): SocialAccount
    {
        $this->platform($platform);

        return SocialAccount::eigen()->where('platform', $platform)->first()
            ?? new SocialAccount(['owner_type' => SocialAccount::EIGEN, 'owner_id' => 0, 'platform' => $platform, 'status' => 'niet_gekoppeld']);
    }

    /** Gekoppeld en bruikbaar (niet verlopen of in de fout). */
    public function actief(string $platform): ?SocialAccount
    {
        $a = $this->account($platform);

        return $a->exists && $a->status === 'gekoppeld' && $a->access_token ? $a : null;
    }

    /** @return list<string> */
    public function scopes(string $platform): array
    {
        return config("kanalen.scopes.{$platform}", []);
    }

    /** Mag het team vanuit MeStudio rechtstreeks op Instagram plaatsen? */
    public function magPlaatsen(): bool
    {
        return (bool) $this->actief('instagram')?->heeftScope('instagram_business_content_publish');
    }

    /* ------------------------------------------------------------------ Koppelen */

    public function autorisatieUrl(string $platform, string $terugUrl, string $state): Resultaat
    {
        return $this->platform($platform)->autorisatieUrl($terugUrl, $state, $this->scopes($platform));
    }

    /** Code inwisselen, tokens opslaan, webhooks aanzetten (Instagram) en direct de eerste keer ophalen. */
    public function koppel(string $platform, string $code, string $terugUrl, User $door): Resultaat
    {
        $resultaat = $this->platform($platform)->wisselCodeIn($code, $terugUrl);
        if (! $resultaat->isGelukt()) {
            return $resultaat;
        }

        $account = $this->account($platform);
        // MEBRANDING koppelt het eigen account: de toestemming is die van het bedrijf zelf.
        $account->fill(['consent_version' => config('content.consent_version'), 'consent_at' => now()]);
        $this->koppelingen->tokensOpslaan($account, $resultaat, $door);

        $platformKlasse = $this->platform($platform);
        if ($platformKlasse instanceof InstagramPlatform) {
            $abonnement = $platformKlasse->abonneerWebhooks($account);
            if (! $abonnement->isGelukt()) {
                Log::warning('Eigen kanalen: webhooks aanzetten mislukt', ['melding' => $abonnement->melding]);
            }
        }

        $this->ververs($account->fresh());

        return $resultaat;
    }

    public function ontkoppel(string $platform, User $door): void
    {
        $account = $this->account($platform);
        if (! $account->exists) {
            return;
        }
        $p = $this->platform($platform);
        if ($p instanceof TikTokPlatform && $account->access_token) {
            $p->intrekken($account);
        }
        $this->koppelingen->verbreken($account, $door);
    }

    /* ------------------------------------------------------------------ Verversen (elk uur) */

    /** Alle gekoppelde eigen kanalen verversen. Geeft per platform de melding terug. */
    public function verversAlles(): array
    {
        $uit = [];
        foreach (SocialAccount::eigen()->where('status', 'gekoppeld')->whereNotNull('access_token')->get() as $account) {
            $uit[$account->platform] = $this->ververs($account)->melding;
        }

        return $uit;
    }

    public function ververs(SocialAccount $account): Resultaat
    {
        $verlengd = $this->verlengIndienNodig($account);
        if (! $verlengd->isGelukt()) {
            return $this->fout($account, $verlengd);
        }

        $platform = $this->platform($account->platform);
        $profiel = $platform->profiel($account);
        if (! $profiel->isGelukt()) {
            return $this->fout($account, $profiel);
        }
        $this->bewaarProfiel($account, $profiel->data);

        $media = $platform->media($account, (int) config('kanalen.media_paginas'));
        if (! $media->isGelukt()) {
            return $this->fout($account, $media);
        }
        $this->bewaarMedia($account, $media->data['items']);

        if ($platform instanceof InstagramPlatform) {
            $this->instagramCijfers($account, $platform);
            if ($account->heeftScope('instagram_business_manage_messages')) {
                $this->instagramGesprekken($account, $platform);
            }
        } else {
            // TikTok kent geen accountcijfers per dag: we bewaren het totaal aan weergaven en rekenen het verschil per dag uit.
            KanaalMeting::zet($account->id, today()->toDateString(), 'video_weergaven_totaal', (int) collect($media->data['items'])->sum(fn ($i) => $i['cijfers']['views'] ?? 0));
        }

        $account->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();

        return Resultaat::gelukt([], "{$platform->naam()} is bijgewerkt.");
    }

    /** Instagram: verlengen vanaf dag 50 van 60. TikTok: toegangstoken (24 uur) bijna verlopen. */
    public function verlengIndienNodig(SocialAccount $account): Resultaat
    {
        $nodig = $account->platform === 'instagram'
            ? $account->expires_at && $account->expires_at->lte(now()->addDays((int) config('kanalen.instagram_verleng_dagen_voor_einde')))
            : ! $account->token_expires_at || $account->token_expires_at->lte(now()->addHours((int) config('kanalen.tiktok_verleng_uren_voor_einde')));
        if (! $nodig) {
            return Resultaat::gelukt();
        }

        $r = $this->platform($account->platform)->vernieuw($account);
        if ($r->isGelukt()) {
            $account->fill(array_filter([
                'access_token' => $r->data['access_token'] ?? null, 'refresh_token' => $r->data['refresh_token'] ?? null,
                'expires_at' => $r->data['expires_at'] ?? null, 'token_expires_at' => $r->data['token_expires_at'] ?? null,
            ]))->save();
            $this->koppelingen->log($account, null, 'koppeling_verlengd', $account->status);
        }

        return $r;
    }

    /** @param  array<string, mixed>  $p */
    private function bewaarProfiel(SocialAccount $account, array $p): void
    {
        $account->fill(array_filter(['handle' => $p['handle'] ?? null, 'external_id' => $p['external_id'] ?? null]) + ['profiel' => ['naam' => $p['naam'] ?? null, 'foto' => $p['foto'] ?? null]])->save();
        $vandaag = today()->toDateString();
        foreach (['volgers' => 'volgers', 'volgend' => 'volgend', 'media' => 'media', 'likes_totaal' => 'likes_totaal'] as $veld => $metriek) {
            if (isset($p[$veld])) {
                KanaalMeting::zet($account->id, $vandaag, $metriek, (int) $p[$veld]);
            }
        }
    }

    /** @param  list<array<string, mixed>>  $items */
    private function bewaarMedia(SocialAccount $account, array $items): void
    {
        foreach ($items as $i) {
            $media = KanaalMedia::firstOrNew(['social_account_id' => $account->id, 'extern_id' => $i['extern_id']]);
            $media->fill([
                'soort' => $i['soort'], 'product' => $i['product'], 'bijschrift' => $i['bijschrift'], 'media_url' => $i['media_url'],
                'miniatuur_url' => $i['miniatuur_url'], 'permalink' => $i['permalink'], 'duur' => $i['duur'],
                'geplaatst_op' => $i['geplaatst_op'] ? Carbon::parse($i['geplaatst_op']) : null,
                // Nieuwe waarden over de oude heen: de medialijst van Instagram heeft alleen likes en reacties.
                'cijfers' => array_merge($media->cijfers ?? [], $i['cijfers']), 'cijfers_op' => now(),
            ])->save();
        }
    }

    private function instagramCijfers(SocialAccount $account, InstagramPlatform $platform): void
    {
        $recent = KanaalMedia::where('social_account_id', $account->id)
            ->where('geplaatst_op', '>=', now()->subDays((int) config('kanalen.inzichten_dagen')))
            ->latest('geplaatst_op')->limit((int) config('kanalen.inzichten_max'))->get();
        foreach ($recent as $media) {
            $r = $platform->mediaInzichten($account, $media->extern_id, $media->product);
            if ($r->isGelukt()) {
                $media->update(['cijfers' => array_merge($media->cijfers ?? [], $r->data), 'cijfers_op' => now()]);
            }
        }

        // Accountcijfers van gisteren (een dag is pas compleet als hij voorbij is), en eenmalig de dagen ervoor tot 28 dagen terug.
        for ($d = 1; $d <= 28; $d++) {
            $dag = today()->subDays($d);
            if (KanaalMeting::where('social_account_id', $account->id)->whereDate('datum', $dag)->where('metriek', 'weergaven')->exists()) {
                break;
            }
            $r = $platform->accountInzichten($account, $dag);
            if (! $r->isGelukt()) {
                break;
            }
            foreach ($r->data as $metriek => $waarde) {
                if ($waarde !== null) {
                    KanaalMeting::zet($account->id, $dag->toDateString(), $metriek, (int) $waarde);
                }
            }
        }
    }

    private function instagramGesprekken(SocialAccount $account, InstagramPlatform $platform): void
    {
        $r = $platform->gesprekken($account, (int) config('kanalen.gesprekken_max'));
        if (! $r->isGelukt()) {
            return;
        }
        foreach ($r->data['items'] as $g) {
            if ($g['deelnemer_id'] === '') {
                continue;
            }
            foreach (array_reverse($g['berichten']) as $b) {
                $this->bewaarBericht($account, $g['deelnemer_id'], $g['deelnemer_naam'], $b['extern_id'], $b['richting'], $b['tekst'], $b['verzonden_op'] ? Carbon::parse($b['verzonden_op']) : now(), null, false, $g['extern_id']);
            }
        }
    }

    /** Reacties van één post ophalen en bewaren. */
    public function verversReacties(KanaalMedia $media): Resultaat
    {
        $platform = $this->platform('instagram');
        if (! $platform instanceof InstagramPlatform || $media->account->platform !== 'instagram') {
            return Resultaat::fout('Reacties lezen kan alleen bij Instagram.');
        }
        $r = $platform->reacties($media->account, $media->extern_id);
        if (! $r->isGelukt()) {
            return $r;
        }
        foreach ($r->data['items'] as $i) {
            KanaalReactie::updateOrCreate(['extern_id' => $i['extern_id']], [
                'kanaal_media_id' => $media->id, 'ouder_extern_id' => $i['ouder_extern_id'], 'gebruikersnaam' => $i['gebruikersnaam'], 'tekst' => $i['tekst'],
                'likes' => $i['likes'], 'geplaatst_op' => $i['geplaatst_op'] ? Carbon::parse($i['geplaatst_op']) : null,
                'eigen' => $media->account->handle !== null && '@'.$i['gebruikersnaam'] === $media->account->handle,
            ]);
        }

        return Resultaat::gelukt();
    }

    /* ------------------------------------------------------------------ Berichten en reacties */

    /**
     * Eén bericht bewaren (uit de webhook, het ophalen of een eigen antwoord). Dubbele berichten (zelfde mid) worden overgeslagen.
     * Een nieuw inkomend bericht telt als ongelezen en geeft het team een melding als het gesprek nog geen ongelezen berichten had.
     */
    public function bewaarBericht(SocialAccount $account, string $deelnemerId, ?string $naam, ?string $mid, string $richting, ?string $tekst, CarbonInterface $op, ?User $door = null, bool $melden = true, ?string $gesprekId = null, ?array $bijlagen = null): ?KanaalBericht
    {
        if ($mid && KanaalBericht::where('extern_id', $mid)->exists()) {
            return null;
        }

        $gesprek = KanaalGesprek::firstOrNew(['social_account_id' => $account->id, 'deelnemer_id' => $deelnemerId]);
        $gesprek->fill(array_filter(['deelnemer_naam' => $naam, 'extern_id' => $gesprekId]));
        $wasOngelezen = $gesprek->ongelezen > 0;
        if (! $gesprek->laatste_bericht_op || $op->gt($gesprek->laatste_bericht_op)) {
            $gesprek->laatste_bericht_op = $op;
        }
        if ($richting === 'in') {
            if (! $gesprek->laatste_inkomend_op || $op->gt($gesprek->laatste_inkomend_op)) {
                $gesprek->laatste_inkomend_op = $op;
            }
            $gesprek->ongelezen = ($gesprek->ongelezen ?? 0) + ($melden ? 1 : 0);
        }
        $gesprek->save();

        $bericht = $gesprek->berichten()->create(['extern_id' => $mid, 'richting' => $richting, 'tekst' => $tekst, 'bijlagen' => $bijlagen, 'verzonden_op' => $op, 'user_id' => $door?->id]);

        if ($melden && $richting === 'in' && ! $wasOngelezen) {
            $this->melder->stuur('t-kanaal-bericht', $this->melder->team('kanalen.beheren'),
                ['afzender' => $naam ? "@{$naam}" : 'iemand', 'tekst' => Str::limit((string) ($tekst ?? 'Bijlage'), 80)],
                route('team.kanalen.berichten', ['gesprek' => $gesprek->id]));
        }

        return $bericht;
    }

    /** Antwoord sturen binnen het 24-uursvenster. */
    public function stuurBericht(KanaalGesprek $gesprek, string $tekst, User $door): Resultaat
    {
        if (! $gesprek->antwoordenTot()) {
            return Resultaat::fout('Antwoorden kan niet meer: Meta staat een antwoord alleen toe binnen 24 uur na het laatste bericht van de ander.');
        }
        $account = $this->actief('instagram');
        $platform = $this->platform('instagram');
        if (! $account || ! $platform instanceof InstagramPlatform) {
            return Resultaat::fout('Instagram is niet gekoppeld.');
        }

        $r = $platform->stuurBericht($account, $gesprek->deelnemer_id, $tekst);
        if ($r->isGelukt()) {
            $this->bewaarBericht($account, $gesprek->deelnemer_id, null, $r->data['extern_id'] ?? null, 'uit', $tekst, now(), $door, false);
        }

        return $r;
    }

    public function beantwoordReactie(KanaalReactie $reactie, string $tekst, User $door): Resultaat
    {
        $account = $this->actief('instagram');
        $platform = $this->platform('instagram');
        if (! $account || ! $platform instanceof InstagramPlatform) {
            return Resultaat::fout('Instagram is niet gekoppeld.');
        }

        $ouder = $reactie->ouder_extern_id ?? $reactie->extern_id;
        $r = $platform->beantwoordReactie($account, $ouder, $tekst);
        if ($r->isGelukt()) {
            KanaalReactie::create([
                'kanaal_media_id' => $reactie->kanaal_media_id, 'extern_id' => $r->data['extern_id'] ?: 'lokaal-'.Str::uuid(), 'ouder_extern_id' => $ouder,
                'gebruikersnaam' => ltrim((string) $account->handle, '@'), 'tekst' => $tekst, 'eigen' => true, 'geplaatst_op' => now(), 'user_id' => $door->id,
            ]);
        }

        return $r;
    }

    /** Fout bij verversen: bij een ongeldig token moet het team opnieuw koppelen (melding), anders later opnieuw proberen. */
    private function fout(SocialAccount $account, Resultaat $r): Resultaat
    {
        $ongeldig = (bool) ($r->data['token_ongeldig'] ?? false);
        $van = $account->status;
        $account->forceFill(['last_error' => Str::limit($r->melding, 250), 'status' => $ongeldig ? 'fout' : $account->status])->save();

        if ($ongeldig && $van !== 'fout') {
            $this->koppelingen->log($account, null, 'koppeling_fout', $van, ['melding' => $r->melding]);
            $this->melder->stuur('t-kanaal-koppeling', $this->melder->team('kanalen.beheren'),
                ['platform' => $this->platform($account->platform)->naam(), 'reden' => $r->melding], route('team.kanalen.index'));
        }

        return $r;
    }
}
