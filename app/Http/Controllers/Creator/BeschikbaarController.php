<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Services\Beschikbaarheid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Creator zet "Ik ben beschikbaar" aan (met of zonder plek) of uit. */
class BeschikbaarController extends Controller
{
    public function __construct(private Beschikbaarheid $beschikbaarheid)
    {
        abort_unless(config('mebranding.creator_kaart'), 404);
    }

    public function aan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'duur' => ['required', 'in:'.implode(',', array_keys(Beschikbaarheid::DUUR))],
            'lat' => ['nullable', 'numeric', 'between:50,54'], // Nederland
            'lng' => ['nullable', 'numeric', 'between:3,8'],
        ]);
        $tot = $this->beschikbaarheid->aan($request->user()->creator, $data['duur'], $data['lat'] ?? null, $data['lng'] ?? null);

        return back()->with('melding', 'Je staat op beschikbaar tot '.$tot->format('H:i').'. Komt er een klus in de buurt, dan krijg je bericht.');
    }

    public function uit(Request $request): RedirectResponse
    {
        $this->beschikbaarheid->uit($request->user()->creator);

        return back()->with('melding', 'Je staat niet meer op beschikbaar.');
    }
}
