<?php

namespace App\Http\Controllers\Team\Kanalen;

use App\Http\Controllers\Controller;
use App\Models\KanaalGesprek;
use App\Models\KanaalMedia;
use App\Models\KanaalMeting;
use App\Models\SocialAccount;
use App\Services\Kanalen\EigenKanalen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Eigen kanalen, overzicht: per platform de status, volgers, weergaven van de laatste 28 dagen met verloop,
 * koppelen en ontkoppelen. Zonder sleutels een checklist met de adressen die in Meta en TikTok horen.
 */
class KanalenController extends Controller
{
    public function index(Request $request, EigenKanalen $k): Response
    {
        return Inertia::render('team/kanalen/overzicht', [
            'kanalen' => collect($k->sleutels())->map(fn (string $p) => $this->kanaal($k, $p))->values(),
            'checklist' => collect($k->sleutels())->mapWithKeys(fn (string $p) => [$p => $this->checklist($request, $k, $p)]),
            'ongelezen' => (int) KanaalGesprek::whereIn('social_account_id', SocialAccount::eigen()->select('id'))->sum('ongelezen'),
        ]);
    }

    /** @return array<string, mixed> */
    private function kanaal(EigenKanalen $k, string $platform): array
    {
        $p = $k->platform($platform);
        $a = $k->account($platform);
        $metingen = $a->exists
            ? KanaalMeting::where('social_account_id', $a->id)->where('datum', '>=', today()->subDays(29))->orderBy('datum')->get()->groupBy('metriek')
            : collect();
        $laatste = fn (string $m) => $metingen->get($m)?->last()?->waarde;
        $reeks = $this->weergavenPerDag($platform, $metingen);
        $volgers = $metingen->get('volgers');

        return [
            'platform' => $platform,
            'naam' => $p->naam(),
            'ingesteld' => $p->isIngesteld(),
            'status' => $this->status($a),
            'handle' => $a->handle,
            'profielnaam' => $a->profiel['naam'] ?? null,
            'foto' => $a->profiel['foto'] ?? null,
            'verloopt_op' => $a->expires_at?->toIso8601String(),
            'laatste_sync' => $a->last_synced_at?->toIso8601String(),
            'fout' => $a->last_error,
            'ontbrekende_rechten' => $a->exists && $a->status === 'gekoppeld' ? array_values(array_diff($k->scopes($platform), $a->scopes ?? [])) : [],
            'volgers' => $laatste('volgers'),
            // Berekend: groei over de periode waarin we metingen hebben (hooguit 28 dagen).
            'volgers_groei' => $volgers && $volgers->count() > 1 ? $volgers->last()->waarde - $volgers->first()->waarde : null,
            'weergaven_28' => $reeks ? array_sum(array_column($reeks, 'waarde')) : null,
            'bereik_gisteren' => $platform === 'instagram' ? $metingen->get('bereik')?->last()?->waarde : null,
            'likes_totaal' => $laatste('likes_totaal'),
            'posts' => $a->exists ? KanaalMedia::where('social_account_id', $a->id)->count() : 0,
            'reeks' => $reeks,
            'berichten' => $platform === 'instagram',
        ];
    }

    /**
     * Weergaven per dag voor de grafiek (laatste 28 dagen). Instagram geeft ze per dag; bij TikTok is het
     * verschil tussen twee dagtotalen van alle video's (berekend, ruwe totalen blijven in kanaal_metingen).
     *
     * @return list<array{datum: string, waarde: int}>
     */
    private function weergavenPerDag(string $platform, $metingen): array
    {
        if ($platform === 'instagram') {
            return ($metingen->get('weergaven') ?? collect())->take(-28)->map(fn ($m) => ['datum' => $m->datum->toDateString(), 'waarde' => $m->waarde])->values()->all();
        }

        $totalen = ($metingen->get('video_weergaven_totaal') ?? collect())->values();
        $uit = [];
        for ($i = 1; $i < $totalen->count(); $i++) {
            $uit[] = ['datum' => $totalen[$i]->datum->toDateString(), 'waarde' => max(0, $totalen[$i]->waarde - $totalen[$i - 1]->waarde)];
        }

        return array_slice($uit, -28);
    }

    /** niet_gekoppeld, gekoppeld, verloopt (binnen de waarschuwingstermijn), verlopen of fout. */
    private function status(SocialAccount $a): string
    {
        if (! $a->exists || $a->status === 'niet_gekoppeld') {
            return 'niet_gekoppeld';
        }

        return match ($a->warning()) {
            'fout' => 'fout',
            'verlopen' => 'verlopen',
            'verloopt' => 'verloopt',
            default => 'gekoppeld',
        };
    }

    /**
     * Wat Dave bij Meta of TikTok invult: de adressen van deze omgeving en de env-variabelen.
     *
     * @return array<string, mixed>
     */
    private function checklist(Request $request, EigenKanalen $k, string $platform): array
    {
        $webhook = Route::has("webhooks.{$platform}") ? route("webhooks.{$platform}")
            : $request->getScheme().'://'.config('mebranding.domains.app').Str::start(trim(config('mebranding.app_prefix').'/webhooks/'.$platform, '/'), '/');

        return [
            'redirect' => route('team.kanalen.terug', $platform),
            'webhook' => $webhook,
            'webhook_actief' => Route::has("webhooks.{$platform}"),
            'scopes' => $k->scopes($platform),
            'env' => $platform === 'instagram'
                ? [['naam' => 'INSTAGRAM_CLIENT_ID', 'ingevuld' => filled(config('services.instagram.client_id'))], ['naam' => 'INSTAGRAM_CLIENT_SECRET', 'ingevuld' => filled(config('services.instagram.client_secret'))], ['naam' => 'INSTAGRAM_WEBHOOK_VERIFY_TOKEN', 'ingevuld' => filled(config('services.instagram.webhook_verify_token'))]]
                : [['naam' => 'TIKTOK_CLIENT_KEY', 'ingevuld' => filled(config('services.tiktok.client_id'))], ['naam' => 'TIKTOK_CLIENT_SECRET', 'ingevuld' => filled(config('services.tiktok.client_secret'))]],
        ];
    }

    /** Naar het platform om in te loggen met het MEBRANDING-account en toestemming te geven. */
    public function koppelen(Request $request, string $platform, EigenKanalen $k): HttpResponse
    {
        $state = Str::random(40);
        $request->session()->put("kanalen.{$platform}", $state);
        $r = $k->autorisatieUrl($platform, route('team.kanalen.terug', $platform), $state);

        return $r->isGelukt() ? Inertia::location($r->data['url']) : back()->with('melding', $r->melding);
    }

    /** Terug van het platform: state controleren, code inwisselen, eerste keer ophalen. */
    public function terug(Request $request, string $platform, EigenKanalen $k): RedirectResponse
    {
        $verwacht = $request->session()->pull("kanalen.{$platform}");
        $naar = to_route('team.kanalen.index');

        if ($request->filled('error') || $request->filled('error_reason')) {
            return $naar->with('melding', 'Koppelen is afgebroken bij '.$k->platform($platform)->naam().'. Probeer het opnieuw.');
        }
        if (! $verwacht || ! hash_equals($verwacht, (string) $request->query('state')) || ! $request->filled('code')) {
            return $naar->with('melding', 'Koppelen is niet gelukt. Probeer het opnieuw.');
        }

        $r = $k->koppel($platform, (string) $request->query('code'), route('team.kanalen.terug', $platform), $request->user());

        return $naar->with('melding', $r->isGelukt() ? $k->platform($platform)->naam().' is gekoppeld.' : $r->melding);
    }

    public function ontkoppelen(Request $request, string $platform, EigenKanalen $k): RedirectResponse
    {
        $k->ontkoppel($platform, $request->user());

        return back()->with('melding', $k->platform($platform)->naam().' is ontkoppeld. Eerder opgehaalde posts en berichten blijven staan.');
    }

    public function verversen(string $platform, EigenKanalen $k): RedirectResponse
    {
        $a = $k->actief($platform);

        return back()->with('melding', $a ? $k->ververs($a)->melding : 'Verversen kan niet: koppel '.$k->platform($platform)->naam().' (opnieuw).');
    }
}
