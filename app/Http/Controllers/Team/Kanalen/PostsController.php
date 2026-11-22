<?php

namespace App\Http\Controllers\Team\Kanalen;

use App\Http\Controllers\Controller;
use App\Models\KanaalMedia;
use App\Models\KanaalReactie;
use App\Models\SocialAccount;
use App\Services\Kanalen\EigenKanalen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Eigen kanalen, posts en filmpjes: raster met cijfers, sorteren en filteren in het scherm, detail met reacties. */
class PostsController extends Controller
{
    /** Hooguit zoveel recente posts in het raster (het scherm toont er eerst 12). */
    private const MAX = 200;

    public function index(Request $request, EigenKanalen $k): Response
    {
        $accounts = SocialAccount::eigen()->get()->keyBy('id');
        $media = KanaalMedia::whereIn('social_account_id', $accounts->keys())->latest('geplaatst_op')->limit(self::MAX)->get();
        $gekozen = $request->integer('post') ?: null;

        return Inertia::render('team/kanalen/posts', [
            'posts' => $media->map(fn (KanaalMedia $m) => $this->post($m, $accounts[$m->social_account_id]))->values(),
            'detail' => fn () => $gekozen ? $this->detail(KanaalMedia::whereIn('social_account_id', $accounts->keys())->findOrFail($gekozen), $k) : null,
            'gekoppeld' => collect($k->sleutels())->mapWithKeys(fn ($p) => [$p => (bool) $k->actief($p)]),
        ]);
    }

    /** @return array<string, mixed> */
    private function post(KanaalMedia $m, SocialAccount $a): array
    {
        return [
            'id' => $m->id, 'platform' => $a->platform, 'soort' => $this->soort($a->platform, $m), 'bijschrift' => $m->bijschrift,
            'miniatuur' => $m->miniatuur_url, 'permalink' => $m->permalink, 'geplaatst_op' => $m->geplaatst_op?->toIso8601String(),
            'cijfers' => [
                'views' => $m->cijfer('views'), 'reach' => $m->cijfer('reach'), 'likes' => $m->cijfer('likes'), 'comments' => $m->cijfer('comments'),
                'saved' => $m->cijfer('saved'), 'shares' => $m->cijfer('shares'),
            ],
        ];
    }

    /** Eén soort per post voor het filter: reel, post, carrousel, story of video (TikTok). */
    private function soort(string $platform, KanaalMedia $m): string
    {
        return match (true) {
            $platform === 'tiktok' => 'video',
            $m->product === 'STORY' => 'story',
            $m->product === 'REELS' || $m->soort === 'VIDEO' => 'reel',
            $m->soort === 'CAROUSEL_ALBUM' => 'carrousel',
            default => 'post',
        };
    }

    /** @return array<string, mixed> */
    private function detail(KanaalMedia $m, EigenKanalen $k): array
    {
        $a = $m->account;

        return $this->post($m, $a) + [
            'media_url' => $m->media_url,
            'duur' => $m->duur,
            'alle_cijfers' => $m->cijfers ?? [],
            'cijfers_op' => $m->cijfers_op?->toIso8601String(),
            'reacties_mogelijk' => $a->platform === 'instagram',
            'mag_antwoorden' => $a->platform === 'instagram' && (bool) $k->actief('instagram')?->heeftScope('instagram_business_manage_comments'),
            'reacties' => $m->reacties()->with('user')->get()->map(fn (KanaalReactie $r) => [
                'id' => $r->id, 'ouder' => $r->ouder_extern_id, 'extern_id' => $r->extern_id, 'naam' => $r->gebruikersnaam, 'tekst' => $r->tekst,
                'likes' => $r->likes, 'eigen' => $r->eigen, 'door' => $r->user?->name, 'op' => $r->geplaatst_op?->toIso8601String(),
            ])->values(),
        ];
    }

    public function reactiesVerversen(KanaalMedia $media, EigenKanalen $k): RedirectResponse
    {
        abort_unless($media->account->owner_type === SocialAccount::EIGEN, 404);
        $r = $k->verversReacties($media);

        return back()->with('melding', $r->isGelukt() ? 'Reacties zijn bijgewerkt.' : $r->melding);
    }

    public function beantwoorden(Request $request, KanaalReactie $reactie, EigenKanalen $k): RedirectResponse
    {
        abort_unless($reactie->media->account->owner_type === SocialAccount::EIGEN, 404);
        $tekst = $request->validate(['tekst' => ['required', 'string', 'max:2200']])['tekst'];
        $r = $k->beantwoordReactie($reactie, $tekst, $request->user());

        return $r->isGelukt() ? back()->with('melding', 'Je antwoord staat onder de post.') : back()->withErrors(['tekst' => $r->melding]);
    }
}
