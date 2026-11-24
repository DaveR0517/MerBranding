<?php

namespace App\Http\Controllers\Team\Kanalen;

use App\Http\Controllers\Controller;
use App\Services\Gmail\Mail;
use App\Services\Gmail\Postvakken;
use App\Services\Gmail\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/** Eigen kanalen, Mail: de Gmail-postvakken van MEBRANDING in het portaal (lezen, beantwoorden, archiveren). */
class MailController extends Controller
{
    public function __construct(private Workspace $workspace, private Postvakken $postvakken, private Mail $mail) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $lijst = $this->postvakken->voor($user);
        abort_if($lijst === [] && ! $user->hasRole('Super Admin'), 403); // eigenaren zien ook zonder postvakken de uitleg

        $zichtbaar = array_values(array_filter($lijst, fn ($p) => $p['zichtbaar']));
        $gekozen = $request->query('postvak');
        $gekozen = collect($zichtbaar)->firstWhere('postvak', $gekozen) ? $gekozen : ($zichtbaar[0]['postvak'] ?? null);
        $fout = null;
        $inbox = ['gesprekken' => [], 'volgende' => null];
        $gesprek = null;

        if ($this->workspace->ingesteld() && $gekozen) {
            try {
                $inbox = $this->mail->gesprekken($gekozen, $request->query('zoek'), $request->query('pagina'));
                if ($id = $request->query('gesprek')) {
                    $gesprek = $this->mail->gesprek($gekozen, $id);
                }
            } catch (Throwable $e) {
                Log::warning('Gmail ophalen mislukt', ['postvak' => $gekozen, 'fout' => $e->getMessage()]);
                $fout = 'Google gaf geen toegang tot '.$gekozen.'. Controleer of de beheerder het portaal toegang gaf (domeinbrede delegatie) en of het postvak bestaat.';
            }
        }

        return Inertia::render('team/kanalen/mail', [
            'ingesteld' => $this->workspace->ingesteld(),
            'postvakken' => $lijst,
            'gekozen' => $gekozen,
            'zoek' => $request->query('zoek'),
            'inbox' => $inbox,
            'gesprek' => $gesprek,
            'fout' => $fout,
        ]);
    }

    public function akkoord(Request $request, string $postvak): RedirectResponse
    {
        $aan = $request->boolean('akkoord');
        $this->postvakken->zetAkkoord($request->user(), strtolower($postvak), $aan, $request->ip());

        return back()->with('melding', $aan ? 'Akkoord vastgelegd: de eigenaren van MEBRANDING kunnen dit postvak in het portaal zien.' : 'Akkoord ingetrokken: alleen jij ziet dit postvak nog.');
    }

    public function beantwoorden(Request $request, string $postvak, string $gesprek): RedirectResponse
    {
        $this->magZien($request, $postvak);
        $data = $request->validate(['tekst' => ['required', 'string', 'max:20000']], ['tekst.required' => 'Schrijf eerst je antwoord.']);
        $this->mail->beantwoorden($postvak, $gesprek, $data['tekst']);

        return back()->with('melding', 'Verstuurd vanaf '.$postvak.'.');
    }

    public function archiveren(Request $request, string $postvak, string $gesprek): RedirectResponse
    {
        $this->magZien($request, $postvak);
        $this->mail->archiveren($postvak, $gesprek);

        return to_route('team.kanalen.mail', ['postvak' => $postvak])->with('melding', 'Gearchiveerd.');
    }

    public function bijlage(Request $request, string $postvak, string $bericht, string $bijlage): HttpResponse
    {
        $this->magZien($request, $postvak);
        $naam = preg_replace('/[^\w.\- ]/u', '_', (string) $request->query('naam', 'bijlage'));

        return response($this->mail->bijlage($postvak, $bericht, $bijlage), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$naam.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function magZien(Request $request, string $postvak): void
    {
        abort_unless($this->postvakken->magZien($request->user(), $postvak), 403);
    }
}
