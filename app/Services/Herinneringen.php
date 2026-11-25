<?php

namespace App\Services;

use App\Models\Collaboration;
use App\Models\Invitation;
use App\Models\Status;
use Illuminate\Support\Collection;

/**
 * Tijdgestuurde herinneringen en teamsignalen van de keten (berichtsjablonen, moment uit FO 3.6 en 8.4).
 * Elke herinnering gaat maar één keer per samenwerking: de sleutel komt in collaborations.herinneringen.
 * Gepland in routes/console.php; elke methode geeft het aantal verstuurde herinneringen terug.
 */
class Herinneringen
{
    public function __construct(private Melder $melder, private Samenwerking $keten) {}

    /** o-bezoek-morgen en c-bezoek-morgen: de dag voor het bezoek (ochtendronde). */
    public function bezoekMorgen(): int
    {
        $morgen = today()->addDay();

        return $this->elk(
            Collaboration::where('status', Status::BezoekGepland)->whereBetween('visit_at', [$morgen, $morgen->copy()->endOfDay()]),
            'bezoek-morgen',
            function (Collaboration $c) {
                $this->melder->stuur('o-bezoek-morgen', $this->keten->ondernemers($c), $this->keten->vars($c), $this->keten->urlOndernemer($c));
                $this->melder->stuur('c-bezoek-morgen', $c->creator?->user, $this->keten->vars($c), $this->keten->urlCreator($c));
            },
        );
    }

    /** c-herinnering-reageer: nog geen antwoord op een open uitnodiging, halverwege de reactietermijn. Eén keer per uitnodiging. */
    public function reageer(): int
    {
        $aantal = 0;
        $open = Invitation::whereNull('answer')->where('expires_at', '>', now())->with('collaboration', 'creator.user')->get();

        foreach ($open as $u) {
            $halverwege = $u->created_at->copy()->addSeconds((int) ($u->created_at->diffInSeconds($u->expires_at) / 2));
            if ($halverwege->isFuture() || ! $this->markeer($u->collaboration, "reageer-{$u->id}")) {
                continue;
            }
            $c = $u->collaboration;
            $this->melder->stuur('c-herinnering-reageer', $u->creator->user, array_merge($this->keten->vars($c, $u->creator), [
                'aantal' => max(1, (int) ceil(now()->diffInMinutes($u->expires_at) / 60)),
            ]), $this->keten->urlCreator($c));
            $aantal++;
        }

        return $aantal;
    }

    /** c-deadline: binnen 48 uur voor de contentdeadline en nog geen versie aangeleverd. */
    public function deadline(): int
    {
        return $this->elk(
            $this->zonderVersie()->where('content_deadline_at', '>', now())
                ->where('content_deadline_at', '<=', now()->addHours(config('mebranding.timers.deadline_reminder_hours'))),
            'deadline',
            fn (Collaboration $c) => $this->melder->stuur('c-deadline', $c->creator->user, array_merge($this->keten->vars($c), [
                'datum' => $c->content_deadline_at->translatedFormat('l j F'),
            ]), $this->keten->urlCreator($c)),
        );
    }

    /** c-te-laat en t-content-laat: de contentdeadline is verstreken zonder versie. */
    public function teLaat(): int
    {
        return $this->elk(
            $this->zonderVersie()->where('content_deadline_at', '<=', now()),
            'te-laat',
            function (Collaboration $c) {
                $this->melder->stuur('c-te-laat', $c->creator->user, $this->keten->vars($c), $this->keten->urlCreator($c));
                $this->melder->stuur('t-content-laat', $this->accountmanager($c), $this->keten->vars($c), $this->keten->urlTeam($c));
            },
        );
    }

    /** c-publiceer-vandaag: op de publicatiedatum (ochtendronde), zolang er geen live-link is. */
    public function publiceerVandaag(): int
    {
        return $this->elk(
            $this->zonderLink()->whereDate('publish_on', today()),
            'publiceer-vandaag',
            fn (Collaboration $c) => $this->melder->stuur('c-publiceer-vandaag', $c->creator->user, $this->keten->vars($c), $this->keten->urlCreator($c)),
        );
    }

    /** c-herinnering-post: twee dagen na de publicatiedatum nog geen live-link (ochtendronde). */
    public function post(): int
    {
        return $this->elk(
            $this->zonderLink()->whereDate('publish_on', '<=', today()->subDays(config('mebranding.timers.post_reminder_days'))),
            'post',
            fn (Collaboration $c) => $this->melder->stuur('c-herinnering-post', $c->creator->user, array_merge($this->keten->vars($c), [
                'datum' => $c->publish_on->translatedFormat('l j F'),
            ]), $this->keten->urlCreator($c)),
        );
    }

    /** t-zoeken-lang: na de aanvraag langer dan de termijn (werkdagen) zonder creator. */
    public function zoekenLang(): int
    {
        $termijn = (int) config('mebranding.timers.zoeken_lang_workdays');

        return $this->elk(
            Collaboration::whereIn('status', [Status::Nieuw, Status::CreatorZoeken])->where('created_at', '<=', now()->subWeekdays($termijn)),
            'zoeken-lang',
            fn (Collaboration $c) => $this->melder->stuur('t-zoeken-lang', $this->melder->team('samenwerkingen.koppelen'), array_merge($this->keten->vars($c), [
                'dagen' => (int) abs($c->created_at->diffInWeekdays(now())),
            ]), $this->keten->urlTeam($c)),
        );
    }

    /** t-controle-lang: de laatste versie staat langer dan de termijn (werkdagen) ter controle. Eén keer per versie. */
    public function controleLang(): int
    {
        $termijn = (int) config('mebranding.timers.controle_lang_workdays');
        $aantal = 0;

        foreach (Collaboration::where('status', Status::TerControle)->with('latestVersion')->get() as $c) {
            $v = $c->latestVersion;
            if (! $v || $v->created_at->copy()->addWeekdays($termijn)->isFuture() || ! $this->markeer($c, "controle-lang-v{$v->version}")) {
                continue;
            }
            $this->melder->stuur('t-controle-lang', $this->melder->team('content.controleren'), $this->keten->vars($c), $this->keten->urlTeam($c));
            $aantal++;
        }

        return $aantal;
    }

    /* ----------------------------------------------------------------- Intern */

    /** Stuurt per samenwerking uit de query één keer (op sleutel) en telt hoe vaak. */
    private function elk($query, string $sleutel, callable $stuur): int
    {
        $aantal = 0;
        foreach ($query->get() as $c) {
            if ($this->markeer($c, $sleutel)) {
                $stuur($c);
                $aantal++;
            }
        }

        return $aantal;
    }

    /** Legt vast dat de herinnering gaat. False als hij al eerder ging. Eerst vastleggen, dan versturen: liever één keer te weinig dan dubbel. */
    private function markeer(Collaboration $c, string $sleutel): bool
    {
        $gehad = $c->herinneringen ?? [];
        if (array_key_exists($sleutel, $gehad)) {
            return false;
        }
        $c->forceFill(['herinneringen' => $gehad + [$sleutel => now()->toIso8601String()]])->save();

        return true;
    }

    private function zonderVersie()
    {
        return Collaboration::where('status', Status::ContentMaken)->whereNotNull('content_deadline_at')->whereDoesntHave('versions');
    }

    private function zonderLink()
    {
        return Collaboration::where('status', Status::Goedgekeurd)->whereNotNull('publish_on')->whereDoesntHave('publications');
    }

    /** De accountmanager van het bedrijf; zonder accountmanager het team dat koppelt. */
    private function accountmanager(Collaboration $c): Collection
    {
        return $c->company->accountManager ? collect([$c->company->accountManager]) : $this->melder->team('samenwerkingen.koppelen');
    }
}
