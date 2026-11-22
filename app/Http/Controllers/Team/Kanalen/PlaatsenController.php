<?php

namespace App\Http\Controllers\Team\Kanalen;

use App\Http\Controllers\Controller;
use App\Jobs\PlaatsOpInstagram;
use App\Models\StudioDesign;
use App\Services\Kanalen\EigenKanalen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Vanuit de feedplanner van MeStudio een ontwerp op het eigen Instagram-account plaatsen. */
class PlaatsenController extends Controller
{
    public function __invoke(Request $request, StudioDesign $ontwerp, EigenKanalen $k): RedirectResponse
    {
        if (! $k->magPlaatsen()) {
            return back()->with('melding', 'Plaatsen kan pas als Instagram gekoppeld is met het recht om te plaatsen.');
        }
        $bestanden = PlaatsOpInstagram::bestanden($ontwerp);
        if (is_string($bestanden)) {
            return back()->withErrors(['plaatsen' => $bestanden]);
        }

        PlaatsOpInstagram::dispatch($ontwerp->id, $request->user()->id);

        return back()->with('melding', 'De post gaat naar Instagram. Je krijgt een melding zodra hij er staat.');
    }
}
