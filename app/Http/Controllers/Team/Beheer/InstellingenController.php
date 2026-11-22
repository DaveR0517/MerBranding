<?php

namespace App\Http\Controllers\Team\Beheer;

use App\Http\Controllers\Controller;
use App\Services\Instellingen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Instellingen (CLAUDE.md 7.4 en 8): niveaus en timers zelf beheren, met VOORSTEL-markering en uitleg per waarde. */
class InstellingenController extends Controller
{
    public function __construct(private Instellingen $instellingen) {}

    public function index(Request $request): Response
    {
        Gate::authorize('instellingen.beheren');

        return Inertia::render('team/beheer/instellingen', [
            'instellingen' => $this->instellingen->catalogus(),
            'is_super_admin' => $request->user()->hasRole(MedewerkersController::SUPER_ADMIN),
        ]);
    }

    public function opslaan(Request $request): RedirectResponse
    {
        Gate::authorize('instellingen.beheren');
        $request->validate(['waarden' => ['required', 'array'], 'voorstel' => ['array']]);

        // Sleutels zijn configpaden met punten: daarom niet via $request->input('voorstel.'.$key).
        $voorstellen = (array) $request->input('voorstel', []);
        $wijzigingen = [];
        foreach ($request->input('waarden') as $key => $invoer) {
            $d = $this->instellingen->definitie($key);
            if (! $d) {
                throw ValidationException::withMessages(['waarden' => 'Onbekende instelling.']);
            }
            $waarde = $this->waarde($d, $invoer, $key);
            $voorstel = filter_var($voorstellen[$key] ?? $d['voorstel'], FILTER_VALIDATE_BOOLEAN);

            if ($d['alleen_super_admin'] && ! $request->user()->hasRole(MedewerkersController::SUPER_ADMIN)) {
                if ($waarde !== config($key)) {
                    abort(403, 'Alleen een Super Admin wijzigt deze instelling.');
                }

                continue;
            }
            $wijzigingen[$key] = [$waarde, $voorstel];
        }

        $aantal = DB::transaction(fn () => collect($wijzigingen)
            ->filter(fn ($w, $key) => $this->instellingen->opslaan($key, $w[0], $w[1], $request->user()))
            ->count());

        return back()->with('melding', $aantal ? "{$aantal} ".($aantal === 1 ? 'instelling' : 'instellingen').' opgeslagen en in het logboek gezet.' : 'Er is niets veranderd.');
    }

    /** Controleert en zet de invoer om naar de waarde zoals hij in de config staat. */
    private function waarde(array $d, mixed $invoer, string $key): int|string|bool
    {
        $fout = fn (string $tekst) => throw ValidationException::withMessages(["waarden.{$key}" => "{$d['label']}: {$tekst}"]);

        return match ($d['type']) {
            'getal' => filter_var($invoer, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10000]]) !== false
                ? (int) $invoer : $fout('vul een heel getal van 0 tot 10000 in.'),
            'bedrag' => is_numeric($euro = str_replace(',', '.', (string) $invoer)) && $euro >= 0 && $euro <= 100000
                ? (int) round((float) $euro * 100) : $fout('vul een bedrag in euro in, bijvoorbeeld 125 of 125,50.'),
            'tijd' => is_string($invoer) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $invoer) ? $invoer : $fout('vul een tijd in als 16:00.'),
            'aan_uit' => filter_var($invoer, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $fout('kies aan of uit.'),
            default => $fout('onbekend soort waarde.'),
        };
    }
}
