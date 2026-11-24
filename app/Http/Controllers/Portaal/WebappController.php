<?php

namespace App\Http\Controllers\Portaal;

use App\Http\Controllers\Controller;
use App\Models\PushAbonnement;
use App\Services\Webpush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** Webapp: manifest en service worker (installeren op het beginscherm) en pushabonnementen per apparaat. */
class WebappController extends Controller
{
    public function __construct(private Webpush $webpush) {}

    /** Manifest: naam, icoon en startpagina. Scope is het portaal (met of zonder /app ervoor). */
    public function manifest(): JsonResponse
    {
        $start = route('start', [], false);

        return response()->json([
            'name' => 'MEBRANDING',
            'short_name' => 'MEBRANDING',
            'description' => 'Je klussen, uitnodigingen en meldingen van MEBRANDING.',
            'id' => $start,
            'start_url' => $start,
            'scope' => rtrim($start, '/').'/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#2E2B48',
            'theme_color' => '#2E2B48',
            'lang' => 'nl',
            'icons' => [
                ['src' => '/brand/app-icoon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/brand/app-icoon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/brand/app-icoon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    /** Service worker: staat in de map van het portaal, zodat hij het hele portaal bedient. */
    public function serviceWorker(): Response
    {
        return response(file_get_contents(resource_path('pwa/sw.js')), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function sleutel(): JsonResponse
    {
        return response()->json(['key' => $this->webpush->publiekeSleutel()]);
    }

    public function abonneren(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:500', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        PushAbonnement::updateOrCreate(['endpoint' => $data['endpoint']], [
            'user_id' => $request->user()->id,
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            'apparaat' => Str::limit((string) $request->userAgent(), 115, ''),
        ]);

        return response()->json(['ok' => true]);
    }

    public function opzeggen(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);
        PushAbonnement::where('user_id', $request->user()->id)->where('endpoint', $data['endpoint'])->delete();

        return response()->json(['ok' => true]);
    }

    /** Proefmelding naar je eigen apparaten, vanaf Account. */
    public function proef(Request $request): JsonResponse
    {
        $aantal = $this->webpush->stuur($request->user(), 'MEBRANDING', 'Meldingen staan aan. Zo zie je straks een nieuwe uitnodiging.', route('start'));

        return response()->json(['verstuurd' => $aantal]);
    }
}
