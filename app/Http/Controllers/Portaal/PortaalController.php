<?php

namespace App\Http\Controllers\Portaal;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DeliverableVersion;
use App\Models\User;
use App\Services\Melder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Gedeeld door alle omgevingen: startpunt, meldingen, video's en Inloggen als. */
class PortaalController extends Controller
{
    /** Na inloggen: naar de eigen omgeving. */
    public function start(Request $request): RedirectResponse|Response
    {
        $omgeving = $request->user()->homePortal();

        return isset(User::OMGEVINGEN[$omgeving]) ? to_route(User::OMGEVINGEN[$omgeving]) : Inertia::render('geen-omgeving');
    }

    public function meldingen(Request $request): Response
    {
        return Inertia::render('meldingen', [
            'notifications' => $request->user()->notifications()->limit(100)->get()->map(fn ($n) => [
                'id' => $n->id, 'title' => $n->data['title'] ?? '', 'body' => $n->data['body'] ?? '', 'url' => $n->data['url'] ?? null,
                'read' => $n->read_at !== null, 'at' => $n->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    public function gelezen(Request $request, string $id): RedirectResponse
    {
        $melding = $request->user()->notifications()->findOrFail($id);
        $melding->markAsRead();

        return $melding->data['url'] ?? null ? redirect()->to($melding->data['url']) : back();
    }

    public function allesGelezen(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /** Video afspelen of downloaden. Alleen voor wie bij de samenwerking hoort. */
    public function video(Request $request, DeliverableVersion $versie): BinaryFileResponse|StreamedResponse
    {
        $c = $versie->collaboration;
        $user = $request->user();
        $magZien = $user->isTeam()
            || $c->creator?->user_id === $user->id
            || ($user->companies()->whereKey($c->company_id)->exists() && ($versie->outcome === 'goedgekeurd' || $c->status->value === 'ter_controle'));
        abort_unless($magZien, 403);

        $disk = Storage::disk($versie->disk);
        abort_unless($disk->exists($versie->path), 404, 'Deze video is niet meer beschikbaar.');
        $naam = "{$c->number}-versie-{$versie->version}.".pathinfo($versie->path, PATHINFO_EXTENSION);

        $download = $request->boolean('download') && ($user->isTeam() || $versie->outcome === 'goedgekeurd' || $c->creator?->user_id === $user->id);

        // Lokale opslag: bestandsantwoord met ondersteuning voor spoelen (Range). Andere opslag (R2, later): streamen.
        if ($versie->disk === 'local') {
            $pad = $disk->path($versie->path);

            return $download
                ? response()->download($pad, $naam)
                : response()->file($pad, ['Content-Type' => $versie->mime ?? 'video/mp4', 'Cache-Control' => 'private, max-age=3600']);
        }

        return $download
            ? $disk->download($versie->path, $naam)
            : $disk->response($versie->path, $naam, ['Content-Type' => $versie->mime ?? 'video/mp4', 'Cache-Control' => 'private, max-age=3600']);
    }

    /** Super Admin bekijkt het platform als een andere gebruiker. Altijd in het logboek. */
    public function inloggenAls(Request $request, User $user, Melder $melder): RedirectResponse
    {
        $door = $request->user();
        abort_unless($door->hasRole('Super Admin'), 403);
        $reden = $request->validate(['reden' => ['nullable', 'string', 'max:255']])['reden'] ?? null;
        ActivityLog::create(['user_id' => $door->id, 'subject_type' => $user->getMorphClass(), 'subject_id' => $user->id, 'event' => 'inloggen_als', 'reason' => $reden ?? $user->name]);

        // t-inloggen-als: de andere Super Admins horen het. Er wordt (nog) geen reden gevraagd; dan staat er dat er geen is.
        $nu = now('Europe/Amsterdam');
        $melder->stuur('t-inloggen-als', $melder->admins()->reject(fn (User $u) => $u->id === $door->id), [
            'wie' => $door->name, 'naam' => $user->name, 'datum' => $nu->translatedFormat('l j F'), 'tijd' => $nu->format('H:i'),
            'reden' => $reden ?? 'geen reden opgegeven',
        ], route('team.logboek'));

        $request->session()->put('inloggen_als_van', $request->user()->id);
        Auth::login($user);

        return to_route('start');
    }

    public function terug(Request $request): RedirectResponse
    {
        $van = $request->session()->pull('inloggen_als_van');
        abort_unless($van, 403);
        Auth::loginUsingId($van);

        return to_route('team.werkvoorraad');
    }
}
