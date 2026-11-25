<?php

namespace App\Services;

use App\Models\Collaboration;
use App\Models\Invitation;
use App\Models\StatSnapshot;
use App\Models\Status;

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
            if ($halverwege->isFuture() || ! $u->collaboration->markeerHerinnering("reageer-{$u->id}")) {
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
                $this->melder->stuur('t-content-laat', $this->keten->accountmanager($c), $this->keten->vars($c), $this->keten->urlTeam($c));
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
            if (! $v || $v->created_at->copy()->addWeekdays($termijn)->isFuture() || ! $c->markeerHerinnering("controle-lang-v{$v->version}")) {
                continue;
            }
            $this->melder->stuur('t-controle-lang', $this->melder->team('content.controleren'), $this->keten->vars($c), $this->keten->urlTeam($c));
            $aantal++;
        }

        return $aantal;
    }

    /** t-bezoek-onbevestigd: het bezoek is morgen en de ondernemer (instelling "zelf bevestigen") heeft het nog niet bevestigd. */
    public function bezoekOnbevestigd(): int
    {
        $morgen = today()->addDay();
        $query = Collaboration::where('status', Status::BezoekGepland)->whereBetween('visit_at', [$morgen, $morgen->copy()->endOfDay()])
            ->whereHas('company', fn ($q) => $q->where('self_confirm_visits', true));
        $aantal = 0;

        foreach ($query->get() as $c) {
            if (! $c->bezoekVastOp()?->isFuture() || ! $c->markeerHerinnering('bezoek-onbevestigd')) {
                continue;
            }
            $this->melder->stuur('t-bezoek-onbevestigd', $this->melder->team('samenwerkingen.plannen'), $this->keten->vars($c), $this->keten->urlTeam($c));
            $aantal++;
        }

        return $aantal;
    }

    /** t-link-ontbreekt: goedgekeurde samenwerkingen zonder link, de termijn van c-herinnering-post na de publicatiedatum. Eén bundel; elke samenwerking telt één keer mee. */
    public function linkOntbreekt(): int
    {
        $nieuw = $this->zonderLink()->whereDate('publish_on', '<=', today()->subDays(config('mebranding.timers.post_reminder_days')))->get()
            ->filter(fn (Collaboration $c) => $c->markeerHerinnering('link-ontbreekt'));

        if ($nieuw->isNotEmpty()) {
            $this->melder->stuur('t-link-ontbreekt', $this->melder->team('koppelingen.beheren'), ['aantal' => $nieuw->count()], route('team.werkvoorraad'));
        }

        return $nieuw->count();
    }

    /**
     * o-eerste-cijfers: 24 uur na Live, zodra er views zijn van de eerste publicatie (over platforms wordt niet opgeteld).
     * Komen er pas na 48 uur cijfers, dan gaat hij niet meer: de tekst zegt "na 24 uur".
     */
    public function eersteCijfers(): int
    {
        $aantal = 0;
        $live = Collaboration::where('status', Status::Live)->whereBetween('live_at', [now()->subHours(48), now()->subHours(24)])->with('publications')->get();

        foreach ($live as $c) {
            $views = $this->eersteViews($c);
            if ($views === null || ! $c->markeerHerinnering('eerste-cijfers')) {
                continue;
            }
            $this->melder->stuur('o-eerste-cijfers', $this->keten->eigenaren($c), array_merge($this->keten->vars($c), [
                'views' => number_format($views, 0, ',', '.'),
                'datum' => $c->measure_until->translatedFormat('l j F'),
            ]), route('ondernemer.resultaten'));
            $aantal++;
        }

        return $aantal;
    }

    /* ----------------------------------------------------------------- Intern */

    /** Views van de eerste publicatie met views, uit de nieuwste geldige meting. */
    private function eersteViews(Collaboration $c): ?int
    {
        foreach ($c->publications->sortBy('id') as $p) {
            $meting = StatSnapshot::geldig()->where('publication_id', $p->id)->where('metric', 'views')->orderByDesc('fetched_at')->orderByDesc('id')->first();
            if ($meting) {
                return (int) $meting->value;
            }
        }

        return null;
    }

    /** Stuurt per samenwerking uit de query één keer (op sleutel) en telt hoe vaak. */
    private function elk($query, string $sleutel, callable $stuur): int
    {
        $aantal = 0;
        foreach ($query->get() as $c) {
            if ($c->markeerHerinnering($sleutel)) {
                $stuur($c);
                $aantal++;
            }
        }

        return $aantal;
    }

    private function zonderVersie()
    {
        return Collaboration::where('status', Status::ContentMaken)->whereNotNull('content_deadline_at')->whereDoesntHave('versions');
    }

    private function zonderLink()
    {
        return Collaboration::where('status', Status::Goedgekeurd)->whereNotNull('publish_on')->whereDoesntHave('publications');
    }
}
