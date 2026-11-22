<?php

namespace App\Http\Controllers\Team\Studio;

use App\Models\StudioDesign;
use App\Services\Kanalen\EigenKanalen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MeStudio, Feedplanner: raster zoals een Instagram-profiel en een maandkalender.
 * Plaatsen op Instagram kan rechtstreeks zodra het eigen account gekoppeld is met het recht om te plaatsen
 * (module eigen kanalen); anders downloaden en handmatig markeren als geplaatst.
 */
class FeedplannerController extends StudioController
{
    private const IN_FEED = ['klaar', 'ingepland', 'geplaatst'];

    public function index(Request $request, EigenKanalen $kanalen): Response
    {
        $maand = rescue(fn () => Carbon::createFromFormat('Y-m', (string) $request->query('maand')), today(), false)->startOfMonth();

        $feed = StudioDesign::with('user')->whereIn('status', self::IN_FEED)
            ->orderByRaw('feed_position is null')->orderByDesc('feed_position')->orderByDesc('updated_at')->get();
        $concepten = StudioDesign::where('status', 'concept')->latest('updated_at')->limit(30)->get();
        $inMaand = StudioDesign::whereBetween('scheduled_at', [$maand->copy()->startOfMonth(), $maand->copy()->endOfMonth()])
            ->orWhereBetween('posted_at', [$maand->copy()->startOfMonth(), $maand->copy()->endOfMonth()])->get();

        return Inertia::render('team/studio/feedplanner', [
            'feed' => $feed->map(fn ($d) => $this->ontwerp($d) + ['laatste_export' => $this->laatsteExport($d)]),
            'concepten' => $concepten->map(fn ($d) => $this->ontwerp($d)),
            'kalender' => $inMaand->map(fn ($d) => $this->ontwerp($d)),
            'maand' => $maand->format('Y-m'),
            'statussen' => config('studio.statussen'),
            'instagram_plaatsen' => $request->user()->can('kanalen.beheren') && $kanalen->magPlaatsen(),
        ]);
    }

    private function laatsteExport(StudioDesign $d): ?array
    {
        $e = $d->exports()->where('status', 'klaar')->orderByRaw("kind = 'mp4' desc")->first();

        return $e ? ExportController::weergave($e) : null;
    }

    /** Nieuwe volgorde van het raster: het eerste id staat linksboven (nieuwste post), zoals op Instagram. */
    public function volgorde(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer', 'distinct']])['ids'];
        $aantal = count($ids);
        foreach (array_values($ids) as $i => $id) {
            StudioDesign::whereKey($id)->update(['feed_position' => $aantal - $i]);
        }

        return back();
    }

    public function bijwerken(Request $request, StudioDesign $ontwerp): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(StudioDesign::STATUSSEN)],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:2200', function ($attr, $waarde, $fail) {
                if (preg_match_all('/(^|\s)#[\p{L}\p{N}_]+/u', (string) $waarde) > 30) {
                    $fail('Instagram staat hooguit 30 hashtags toe.');
                }
            }],
            'post_url' => ['sometimes', 'nullable', 'url', 'max:255'],
        ], ['caption.max' => 'Een bijschrift op Instagram is hooguit 2200 tekens.']);

        if (($data['status'] ?? null) === 'ingepland' && empty($data['scheduled_at'] ?? $ontwerp->scheduled_at)) {
            return back()->withErrors(['scheduled_at' => 'Kies een datum om in te plannen.']);
        }
        if (($data['status'] ?? null) === 'geplaatst' && ! $ontwerp->posted_at) {
            $data['posted_at'] = now();
        }
        if (in_array($data['status'] ?? null, self::IN_FEED, true) && $ontwerp->feed_position === null) {
            $data['feed_position'] = (int) StudioDesign::max('feed_position') + 1;
        }
        $ontwerp->update($data);

        return back()->with('melding', 'Post bijgewerkt.');
    }
}
