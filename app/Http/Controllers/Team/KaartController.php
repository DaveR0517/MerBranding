<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Collaboration;
use App\Models\Creator;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Status;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kaart voor het team: aangesloten zaken (vestigingen), aanmeldingen van ondernemers en open klussen.
 * Basis voor de creatorkaart met klussen in de buurt (docs/plan-kaart-en-snelle-klussen.md).
 */
class KaartController extends Controller
{
    private const OPEN = [Status::Nieuw, Status::CreatorZoeken];

    public function index(): Response
    {
        Gate::authorize('bedrijven.bekijken');

        $klussen = Collaboration::whereNotIn('status', [Status::Afgerond->value, Status::Geannuleerd->value, Status::NietVerschenen->value])
            ->get(['id', 'location_id', 'status', 'title', 'number'])->groupBy('location_id');

        $vestigingen = Location::with(['company.subscriptions'])->get()->map(function (Location $v) use ($klussen) {
            $bedrijf = $v->company;
            $hier = $klussen[$v->id] ?? collect();
            $open = $hier->filter(fn ($c) => in_array($c->status, self::OPEN, true));

            return [
                'id' => $v->id, 'company_id' => $bedrijf->id, 'bedrijf' => $bedrijf->name, 'naam' => $v->name,
                'adres' => trim(($v->address ?? '').', '.($v->city ?? ''), ', '), 'branche' => $bedrijf->branche,
                'lat' => $v->lat, 'lng' => $v->lng, 'precisie' => $v->geo_precisie, 'gezocht' => $v->geocoded_at !== null,
                'soort' => $bedrijf->onbeperkt_tegoed ? 'eigen' : ($bedrijf->subscriptions->contains('status', 'actief') ? 'klant' : 'zonder'),
                'open_klussen' => $open->map(fn ($c) => ['id' => $c->id, 'titel' => $c->title, 'nummer' => $c->number, 'status' => $c->status->label()])->values(),
                'lopend' => $hier->count() - $open->count(),
            ];
        })->values();

        $aanmeldingen = Lead::where('type', Lead::TYPE_ONDERNEMER)->where('status', 'nieuw')->latest()->get()->map(fn (Lead $l) => [
            'id' => $l->id, 'naam' => $l->name, 'bedrijf' => $l->company, 'plaats' => $l->city, 'adres' => $l->data['adres'] ?? null,
            'branche' => $l->data['branche'] ?? null, 'aangemeld' => $l->created_at->toIso8601String(),
            'lat' => $l->lat, 'lng' => $l->lng, 'precisie' => $l->geo_precisie, 'gezocht' => $l->geocoded_at !== null,
        ])->values();

        // Creators die nu beschikbaar zijn (alleen met de creatorkant aan, config mebranding.creator_kaart).
        $creators = ! config('mebranding.creator_kaart') ? [] : Creator::with('user')->where('beschikbaar_tot', '>', now())->get()->map(fn (Creator $c) => [
            'id' => $c->id, 'naam' => $c->user->name, 'niveau' => $c->levelName(), 'plaats' => $c->city,
            'tot' => $c->beschikbaar_tot->toIso8601String(), 'lat' => $c->beschikbaar_lat, 'lng' => $c->beschikbaar_lng,
        ])->values();

        return Inertia::render('team/kaart', ['vestigingen' => $vestigingen, 'aanmeldingen' => $aanmeldingen, 'creators' => $creators]);
    }
}
