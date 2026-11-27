<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Http\Presenters\SamenwerkingPresenter;
use App\Models\Collaboration;
use App\Models\Creator;
use App\Models\Invitation;
use App\Models\Signature;
use App\Services\Samenwerking;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Omgeving van de creator. */
class CreatorController extends Controller
{
    public function __construct(private Samenwerking $dienst) {}

    public function overzicht(Request $request): Response
    {
        $creator = $this->creator($request);

        return Inertia::render('creator/overzicht', [
            'raamovereenkomst_getekend' => Signature::where('user_id', $request->user()->id)->where('agreement', 'raamovereenkomst')->exists(),
            'creator' => ['name' => $request->user()->name, 'level' => $creator->levelName(), 'reward' => $creator->feeCents() > 0 ? 'Gratis bezoek en € '.number_format($creator->feeCents() / 100, 0, ',', '.').' per samenwerking' : 'Gratis bezoek per samenwerking'],
            'invitations' => $creator->invitations()->with('collaboration.company', 'collaboration.location')->whereNull('answer')->where('expires_at', '>', now())->get()
                ->map(fn (Invitation $i) => [
                    'id' => $i->id, 'collaboration_id' => $i->collaboration_id, 'company' => $i->collaboration->company->name,
                    'location' => $i->collaboration->location->name, 'title' => $i->collaboration->title, 'expires_at' => $i->expires_at->toIso8601String(),
                ])->values(),
            'jobs' => $creator->collaborations()->with(['company', 'location', 'creator.user'])->latest('updated_at')->get()
                ->map(fn ($c) => SamenwerkingPresenter::kort($c))->values(),
            'payouts' => $creator->payouts()->with('collaboration.company')->latest()->get()->map(fn ($p) => [
                'company' => $p->collaboration->company->name, 'amount_cents' => $p->amount_cents, 'status' => $p->status, 'run_on' => $p->run_on->toDateString(),
            ])->values(),
        ]);
    }

    public function klus(Request $request, Collaboration $samenwerking): Response
    {
        $creator = $this->creator($request);
        $uitnodiging = $samenwerking->invitations()->where('creator_id', $creator->id)->latest()->first();
        abort_unless($samenwerking->creator_id === $creator->id || $uitnodiging?->isOpen(), 403);

        return Inertia::render('creator/klus', [
            'collaboration' => SamenwerkingPresenter::vol($samenwerking, 'creator', $request->user()),
            'invitation' => $uitnodiging?->isOpen() ? ['id' => $uitnodiging->id, 'expires_at' => $uitnodiging->expires_at->toIso8601String()] : null,
            'contract' => [
                'version' => config('mebranding.contract_version'),
                'text' => 'Kluscontract (versie '.config('mebranding.contract_version').'). Je bezoekt '.$samenwerking->company->name.' ('.$samenwerking->location->name.'), maakt een video volgens de briefing, levert die aan voor controle en plaatst hem op de afgesproken datum op je eigen account, met de reclamevermelding "Betaalde samenwerking". Je komt op het afgesproken moment; kun je toch niet, meld het dan zo vroeg mogelijk. Daarnaast gelden de basisafspraken uit je raamovereenkomst.',
            ],
            'video_max_mb' => config('mebranding.video_max_mb'),
        ]);
    }

    public function accepteren(Request $request, Invitation $uitnodiging): RedirectResponse
    {
        $data = $request->validate(['signed_name' => ['required', 'string', 'max:120'], 'akkoord' => ['accepted']], ['akkoord.accepted' => 'Ga akkoord met het contract.']);
        $this->dienst->accepteren($uitnodiging, $request->user(), $data['signed_name'], (string) $request->ip());

        return to_route('creator.klus', $uitnodiging->collaboration_id)->with('melding', 'Contract getekend. Kies nu een tijdvak voor je bezoek.');
    }

    public function weigeren(Request $request, Invitation $uitnodiging): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->dienst->weigeren($uitnodiging, $request->user(), $data['reason'] ?? null);

        return to_route('creator.overzicht')->with('melding', 'Je hebt de uitnodiging afgeslagen. Dat heeft geen gevolg voor je score.');
    }

    public function tijdvak(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        $data = $request->validate(['moment' => ['required', 'date']]);
        $this->dienst->tijdvakKiezen($samenwerking, $request->user(), CarbonImmutable::parse($data['moment'], config('app.timezone')));

        return back()->with('melding', 'Je bezoek staat vast.');
    }

    public function inchecken(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        $this->dienst->inchecken($samenwerking, $request->user());

        return back()->with('melding', 'Je bent ingecheckt. Veel plezier!');
    }

    public function klaar(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        $this->dienst->klaarMetFilmen($samenwerking, $request->user());

        return back()->with('melding', 'Top. Lever je video aan voor de deadline.');
    }

    public function indienen(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        $data = $request->validate([
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm,video/x-m4v', 'max:'.(config('mebranding.video_max_mb') * 1024)],
            'caption' => ['required', 'string', 'max:2200'],
            'label_platform' => ['boolean'],
            'label_beeld' => ['boolean'],
        ], ['video.mimetypes' => 'Upload een video (mp4, mov of webm).', 'video.max' => 'De video is te groot.']);

        $this->dienst->indienen($samenwerking, $request->user(), $data['video'], $data['caption'], [
            'label_platform' => $request->boolean('label_platform'), 'label_beeld' => $request->boolean('label_beeld'),
        ]);

        return back()->with('melding', 'Je video is ingediend. Het team controleert hem vandaag nog.');
    }

    public function live(Request $request, Collaboration $samenwerking): RedirectResponse
    {
        $data = $request->validate([
            'platform' => ['required', 'in:instagram,tiktok'],
            'url' => ['required', 'url:https', 'regex:/(instagram\.com|tiktok\.com)/i'],
        ], ['url.regex' => 'Plak de link van je post op Instagram of TikTok.']);
        $this->dienst->liveKoppelen($samenwerking, $request->user(), $data['platform'], $data['url']);

        return back()->with('melding', 'Je post staat live. We meten 14 dagen.');
    }

    private function creator(Request $request): Creator
    {
        return $request->user()->creator()->firstOrFail();
    }
}
