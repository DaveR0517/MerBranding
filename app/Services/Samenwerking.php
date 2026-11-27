<?php

namespace App\Services;

use App\Events\SamenwerkingGewijzigd;
use App\Models\ActivityLog;
use App\Models\Collaboration;
use App\Models\Company;
use App\Models\Creator;
use App\Models\CreditLedger;
use App\Models\DeliverableVersion;
use App\Models\Feedback;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\Payout;
use App\Models\Signature;
use App\Models\Status;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * De state machine van een samenwerking (CLAUDE.md hoofdstuk 6).
 * Elke overgang: controle of hij mag, logboekregel, tegoed, meldingen en werkvoorraad.
 */
class Samenwerking
{
    /** Toegestane overgangen. Annuleren en Niet verschenen hebben eigen methodes. */
    private const OVERGANGEN = [
        'nieuw' => ['creator_zoeken'],
        'creator_zoeken' => ['creator_gevonden'],
        'creator_gevonden' => ['bezoek_gepland'],
        'bezoek_gepland' => ['bezoek_geweest'],
        'bezoek_geweest' => ['content_maken'],
        'content_maken' => ['ter_controle'],
        'ter_controle' => ['feedback', 'goedgekeurd'],
        'feedback' => ['ter_controle'],
        'goedgekeurd' => ['live'],
        'live' => ['afgerond'],
    ];

    public function __construct(private Melder $melder, private Werkvoorraad $werk) {}

    /* ----------------------------------------------------------------- 1. Nieuw */

    /** @param array{location_id: int, title: string, briefing: string, period_from?: ?string, period_to?: ?string, alcohol?: bool, creator_level: string} $data */
    /** Nieuwe aanvraag. Met $creator kiest de ondernemer zelf en gaat de uitnodiging direct naar die creator; anders koppelt het team. */
    public function aanvragen(Company $company, User $door, array $data, ?Creator $creator = null): Collaboration
    {
        $location = Location::where('company_id', $company->id)->findOrFail($data['location_id']);
        $points = (int) config("mebranding.levels.{$data['creator_level']}.points");

        if ($points < 1) {
            throw ValidationException::withMessages(['creator_level' => 'Kies een niveau.']);
        }
        if (! $company->kanBetalen($points)) {
            throw ValidationException::withMessages(['creator_level' => "Je tegoed is te laag: je hebt {$company->availablePoints()} punten, deze samenwerking kost {$points} punten."]);
        }

        return DB::transaction(function () use ($company, $door, $data, $location, $points, $creator) {
            $c = Collaboration::create([
                'number' => 'S'.now()->format('ymd').'-'.str_pad((string) (Collaboration::count() + 1), 4, '0', STR_PAD_LEFT),
                'company_id' => $company->id,
                'location_id' => $location->id,
                'requested_by' => $door->id,
                'title' => $data['title'],
                'briefing' => $data['briefing'],
                'period_from' => $data['period_from'] ?? null,
                'period_to' => $data['period_to'] ?? null,
                'alcohol' => (bool) ($data['alcohol'] ?? false),
                'status' => Status::Nieuw,
                'points' => $points,
                'creator_level' => $data['creator_level'],
            ]);

            $this->boek($c, CreditLedger::RESERVEREN, -$points, 'Aanvraag verstuurd: punten gereserveerd', $door);
            $this->logboek($c, $door, 'aangevraagd', null, Status::Nieuw);

            if ($creator) {
                $this->uitnodigen($c, $creator, $door);
            } else {
                $this->melder->stuur('o-aangevraagd', $this->ondernemers($c), array_merge($this->vars($c), ['aantal' => 2]), $this->urlOndernemer($c));
                $this->werk->open('koppelen', "Creator koppelen: {$company->name}, {$c->title}", $c, 'samenwerkingen.koppelen', now()->addWeekdays(2));
                $this->melder->stuur('t-nieuw', $this->melder->team('samenwerkingen.koppelen'), $this->vars($c), $this->urlTeam($c));
            }

            return $c;
        });
    }

    /* ----------------------------------------------------------------- 2. Creator zoeken */

    public function uitnodigen(Collaboration $c, Creator $creator, User $door): Invitation
    {
        $this->vereisStatus($c, [Status::Nieuw, Status::CreatorZoeken]);

        if ($c->openInvitation()) {
            throw ValidationException::withMessages(['creator' => 'Er staat al een open uitnodiging. Wacht op antwoord of trek die eerst in.']);
        }
        if ($c->alcohol && ($creator->age() ?? 0) < 25) {
            throw ValidationException::withMessages(['creator' => 'Bij alcohol in beeld alleen creators van 25 jaar of ouder.']);
        }
        if ($creator->status !== 'actief') {
            throw ValidationException::withMessages(['creator' => 'Deze creator is niet actief.']);
        }
        if ($c->invitations()->where('creator_id', $creator->id)->where('answer', 'geweigerd')->exists()) {
            throw ValidationException::withMessages(['creator' => "{$creator->user->name} heeft deze uitnodiging al afgeslagen. Kies iemand anders."]);
        }

        return DB::transaction(function () use ($c, $creator, $door) {
            if ($c->status === Status::Nieuw) {
                $this->naar($c, Status::CreatorZoeken, $door);
            }

            $uitnodiging = $c->invitations()->create([
                'creator_id' => $creator->id,
                'invited_by' => $door->id,
                'expires_at' => now()->addHours(config('mebranding.timers.invitation_hours')),
            ]);
            $c->unsetRelation('invitations');

            $this->logboek($c, $door, 'uitgenodigd', null, null, null, ['creator' => $creator->user->name]);
            $this->werk->sluit($c, ['koppelen', 'geweigerd']);
            $this->melder->stuur('c-uitnodiging', $creator->user, array_merge($this->vars($c, $creator), ['aantal' => config('mebranding.timers.invitation_hours')]), $this->urlCreator($c));

            return $uitnodiging;
        });
    }

    /* ----------------------------------------------------------------- 3. Creator gevonden (W1: tekenen = accepteren) */

    public function accepteren(Invitation $uitnodiging, User $door, string $naam, string $ip): void
    {
        $c = $uitnodiging->collaboration;
        $creator = $uitnodiging->creator;
        $this->vereisStatus($c, [Status::CreatorZoeken]);

        if (! $uitnodiging->isOpen() || $creator->user_id !== $door->id) {
            throw ValidationException::withMessages(['uitnodiging' => 'Deze uitnodiging is niet meer geldig.']);
        }

        DB::transaction(function () use ($c, $creator, $uitnodiging, $door, $naam, $ip) {
            $uitnodiging->update(['answer' => 'geaccepteerd', 'answered_at' => now()]);
            Signature::create([
                'user_id' => $door->id, 'collaboration_id' => $c->id, 'agreement' => 'kluscontract',
                'version' => config('mebranding.contract_version'), 'signed_name' => $naam, 'ip' => $ip,
            ]);

            // Ander niveau dan aangevraagd: verschil in punten bijboeken of vrijgeven.
            $verschil = $creator->points() - $c->points;
            if ($verschil !== 0) {
                $this->boek($c, CreditLedger::RESERVEREN, -$verschil, "Niveau {$creator->levelName()}: reservering aangepast", $door);
            }

            $c->forceFill([
                'creator_id' => $creator->id,
                'creator_level' => $creator->level,
                'points' => $creator->points(),
                'fee_cents' => $creator->feeCents(),
            ]);
            $this->naar($c, Status::CreatorGevonden, $door, 'Contract getekend');

            $this->melder->stuur('o-creator-gekozen', $this->ondernemers($c), $this->vars($c), $this->urlOndernemer($c));
            $this->melder->stuur('c-tijdvak', $creator->user, $this->vars($c), $this->urlCreator($c));
        });
    }

    public function weigeren(Invitation $uitnodiging, User $door, ?string $reden): void
    {
        $c = $uitnodiging->collaboration;
        if (! $uitnodiging->isOpen() || $uitnodiging->creator->user_id !== $door->id) {
            throw ValidationException::withMessages(['uitnodiging' => 'Deze uitnodiging is niet meer geldig.']);
        }

        // Weigeren heeft geen gevolg voor de score (CLAUDE.md 7.3).
        $uitnodiging->update(['answer' => 'geweigerd', 'answered_at' => now()]);
        $this->logboek($c, $door, 'uitnodiging_geweigerd', null, null, $reden);
        $this->werk->open('geweigerd', "Creator weigerde: {$c->company->name}, opnieuw koppelen", $c, 'samenwerkingen.koppelen', now()->addWeekday());
        $this->melder->stuur('t-geweigerd', $this->melder->team('samenwerkingen.koppelen'), $this->vars($c, $uitnodiging->creator), $this->urlTeam($c));
    }

    /** Uitnodigingen zonder antwoord na de reactietermijn: verlopen, en een taak om opnieuw te koppelen. */
    public function verlopenUitnodigingen(): int
    {
        $verlopen = Invitation::whereNull('answer')->where('expires_at', '<=', now())->with('collaboration.company', 'creator.user')->get();

        foreach ($verlopen as $u) {
            $u->update(['answer' => 'verlopen', 'answered_at' => now()]);
            $c = $u->collaboration;
            $this->logboek($c, null, 'uitnodiging_verlopen', null, null, null, ['creator' => $u->creator->user->name]);
            if ($c->status === Status::CreatorZoeken) {
                $this->werk->open('geweigerd', "Geen reactie van {$u->creator->user->name}: {$c->company->name}, opnieuw koppelen", $c, 'samenwerkingen.koppelen', now()->addWeekday());
                $this->melder->stuur('t-geweigerd', $this->melder->team('samenwerkingen.koppelen'), $this->vars($c, $u->creator), $this->urlTeam($c));
            }
        }

        return $verlopen->count();
    }

    /* ----------------------------------------------------------------- 4. Bezoek gepland (W2) */

    public function tijdvakKiezen(Collaboration $c, User $door, CarbonImmutable $moment): void
    {
        $this->vereisCreator($c, $door);
        $this->vereisStatus($c, [Status::CreatorGevonden]);

        $past = $c->location->visitWindows->contains(fn ($w) => $w->weekday === $moment->dayOfWeekIso
            && $moment->format('H:i') >= substr($w->starts_at, 0, 5)
            && $moment->format('H:i') < substr($w->ends_at, 0, 5));

        if (! $past || $moment->isPast()) {
            throw ValidationException::withMessages(['moment' => 'Kies een moment in de toekomst binnen de tijden van de vestiging.']);
        }

        $c->forceFill(['visit_at' => $moment]);
        $this->naar($c, Status::BezoekGepland, $door);

        $sjabloon = $c->company->self_confirm_visits ? 'o-bezoek-bevestigen' : 'o-bezoek-vast';
        $this->melder->stuur($sjabloon, $this->ondernemers($c), $this->vars($c), $this->urlOndernemer($c));
        $this->melder->stuur('c-bezoek-vast', $door, $this->vars($c), $this->urlCreator($c));
    }

    /* ----------------------------------------------------------------- 5. Bezoek geweest: punten verbruikt */

    public function inchecken(Collaboration $c, User $door): void
    {
        $this->vereisCreator($c, $door);
        $this->vereisStatus($c, [Status::BezoekGepland]);

        if (! $c->visit_at->isToday()) {
            throw ValidationException::withMessages(['checkin' => 'Inchecken kan alleen op de dag van het bezoek ('.$c->visit_at->translatedFormat('j F').').']);
        }

        DB::transaction(function () use ($c, $door) {
            $c->forceFill(['visited_at' => now()]);
            $this->naar($c, Status::BezoekGeweest, $door, 'Ingecheckt: Ik ben er');
            $this->boek($c, CreditLedger::VERBRUIKEN, 0, "Bezoek geweest: {$c->points} gereserveerde punten verbruikt", $door);
        });
    }

    /**
     * Team: het bezoek was er wel, maar de creator vergat in te checken. Zelfde boeking als inchecken,
     * en direct door naar Content maken met de gewone deadline, zodat de klus niet blijft hangen.
     */
    public function bezoekBevestigen(Collaboration $c, User $door): void
    {
        $this->vereisStatus($c, [Status::BezoekGepland]);

        if ($c->visit_at->isAfter(now()->endOfDay())) {
            throw ValidationException::withMessages(['bezoek' => 'Een bezoek bevestigen kan pas op de dag van het bezoek ('.$c->visit_at->translatedFormat('j F').').']);
        }

        DB::transaction(function () use ($c, $door) {
            $c->forceFill(['visited_at' => $c->visit_at]);
            $this->naar($c, Status::BezoekGeweest, $door, 'Bezoek bevestigd door het team (creator checkte niet in)');
            $this->boek($c, CreditLedger::VERBRUIKEN, 0, "Bezoek geweest: {$c->points} gereserveerde punten verbruikt", $door);

            $c->forceFill(['content_deadline_at' => now()->addDays(config('mebranding.timers.content_deadline_days'))->setTime(23, 59)]);
            $this->naar($c, Status::ContentMaken, $door);
        });
    }

    /* ----------------------------------------------------------------- 6. Content maken */

    public function klaarMetFilmen(Collaboration $c, User $door): void
    {
        $this->vereisCreator($c, $door);
        $this->vereisStatus($c, [Status::BezoekGeweest]);

        $c->forceFill(['content_deadline_at' => now()->addDays(config('mebranding.timers.content_deadline_days'))->setTime(23, 59)]);
        $this->naar($c, Status::ContentMaken, $door);
    }

    /* ----------------------------------------------------------------- 7. Ter controle */

    /** @param array{label_platform: bool, label_beeld: bool} $bevestigd */
    public function indienen(Collaboration $c, User $door, UploadedFile $video, string $bijschrift, array $bevestigd): DeliverableVersion
    {
        $this->vereisCreator($c, $door);
        $this->vereisStatus($c, [Status::ContentMaken, Status::Feedback]);

        $controle = self::controleerReclame($bijschrift, $c->company->name, $bevestigd);
        if (! $controle['geslaagd']) {
            throw ValidationException::withMessages(['caption' => $controle['uitleg']]);
        }

        return DB::transaction(function () use ($c, $door, $video, $bijschrift, $controle) {
            $versie = ($c->versions()->max('version') ?? 0) + 1;
            $disk = config('mebranding.video_disk');
            $pad = $video->storeAs("videos/{$c->number}", "versie-{$versie}-".Str::random(8).'.'.($video->getClientOriginalExtension() ?: 'mp4'), $disk);

            $v = $c->versions()->create([
                'version' => $versie, 'disk' => $disk, 'path' => $pad, 'mime' => $video->getMimeType(),
                'size' => $video->getSize(), 'caption' => $bijschrift, 'label_check' => $controle, 'submitted_by' => $door->id,
            ]);

            $this->naar($c, Status::TerControle, $door, "Versie {$versie} ingediend");

            $deadline = Werkvoorraad::controleDeadline(now());
            $this->werk->open('contentcontrole', "Controleren: {$c->company->name}, versie {$versie}", $c, 'content.controleren', $deadline);
            $this->melder->stuur('t-ter-controle', $this->melder->team('content.controleren'), $this->vars($c), $this->urlTeam($c));
            $this->melder->stuur('o-meekijken', $this->eigenaren($c), $this->vars($c), $this->urlOndernemer($c));

            return $v;
        });
    }

    /**
     * Controle reclamevermelding (CLAUDE.md 8, RSM per 1 juli 2026): "Advertentie" of "Betaalde samenwerking (met ...)" aan het begin
     * van het bijschrift, plus de bevestiging dat het platformlabel aan staat en er tekst in beeld is.
     *
     * @return array{geslaagd: bool, bijschrift: bool, platformlabel: bool, tekst_in_beeld: bool, uitleg: string}
     */
    public static function controleerReclame(string $bijschrift, string $bedrijf, array $bevestigd): array
    {
        $begin = mb_strtolower(ltrim($bijschrift));
        $bijschriftOk = str_starts_with($begin, 'advertentie') || str_starts_with($begin, 'betaalde samenwerking');
        $platform = (bool) ($bevestigd['label_platform'] ?? false);
        $beeld = (bool) ($bevestigd['label_beeld'] ?? false);

        $mist = array_filter([
            $bijschriftOk ? null : "begin het bijschrift met \"Advertentie\" of \"Betaalde samenwerking met {$bedrijf}\"",
            $platform ? null : 'zet het label "Betaalde samenwerking" (Instagram) of de schakelaar commerciële content (TikTok) aan',
            $beeld ? null : 'zet de vermelding ook als tekst in beeld',
        ]);

        return [
            'geslaagd' => $mist === [],
            'bijschrift' => $bijschriftOk,
            'platformlabel' => $platform,
            'tekst_in_beeld' => $beeld,
            'uitleg' => $mist === [] ? 'Reclamevermelding in orde.' : 'Reclamevermelding niet compleet: '.implode('; ', $mist).'.',
        ];
    }

    /* ----------------------------------------------------------------- 8. Feedback */

    /** @param list<array{timecode: ?int, body: string}> $punten */
    public function feedbackSturen(Collaboration $c, User $door, array $punten): void
    {
        $this->vereisStatus($c, [Status::TerControle]);
        if ($punten === []) {
            throw ValidationException::withMessages(['punten' => 'Voeg minimaal één opmerking toe.']);
        }

        DB::transaction(function () use ($c, $door, $punten) {
            $versie = $c->latestVersion;
            foreach ($punten as $p) {
                $versie->feedback()->create(['user_id' => $door->id, 'timecode' => $p['timecode'], 'body' => $p['body'], 'visible_to_creator' => true]);
            }
            $versie->update(['outcome' => 'feedback']);

            $this->naar($c, Status::Feedback, $door, count($punten).' opmerkingen');
            $this->werk->sluit($c, 'contentcontrole');
            $this->melder->stuur('c-feedback', $c->creator->user, array_merge($this->vars($c), [
                'aantal' => count($punten), 'datum' => $c->content_deadline_at?->translatedFormat('l j F') ?? 'de deadline',
            ]), $this->urlCreator($c));
        });
    }

    /** De ondernemer kijkt mee en plaatst een opmerking op tijdcode. Het team beslist (W3 optie B); de creator ziet hem niet direct. */
    public function opmerkingOndernemer(Collaboration $c, User $door, ?int $timecode, string $tekst): Feedback
    {
        $this->vereisStatus($c, [Status::TerControle]);
        $f = $c->latestVersion->feedback()->create(['user_id' => $door->id, 'timecode' => $timecode, 'body' => $tekst, 'visible_to_creator' => false]);
        $this->logboek($c, $door, 'opmerking_ondernemer', null, null, null, ['timecode' => $timecode]);

        return $f;
    }

    /* ----------------------------------------------------------------- 9. Goedgekeurd (W4) */

    public function goedkeuren(Collaboration $c, User $door, CarbonImmutable $publicatiedatum): void
    {
        $this->vereisStatus($c, [Status::TerControle]);
        if ($publicatiedatum->lt(today())) {
            throw ValidationException::withMessages(['publish_on' => 'Kies vandaag of een datum in de toekomst.']);
        }

        DB::transaction(function () use ($c, $door, $publicatiedatum) {
            $c->latestVersion->update(['outcome' => 'goedgekeurd']);
            $c->forceFill(['publish_on' => $publicatiedatum]);
            $this->naar($c, Status::Goedgekeurd, $door, 'Publicatiedatum '.$publicatiedatum->format('d-m-Y'));
            $this->werk->sluit($c, 'contentcontrole');

            $datum = $publicatiedatum->translatedFormat('l j F');
            $this->melder->stuur('c-goedgekeurd', $c->creator->user, array_merge($this->vars($c), ['datum' => $datum]), $this->urlCreator($c));
            $this->melder->stuur('o-goedgekeurd', $this->eigenaren($c), array_merge($this->vars($c), ['datum' => $datum]), $this->urlOndernemer($c));
        });
    }

    /* ----------------------------------------------------------------- 10. Live (W5, W6, W7) */

    public function liveKoppelen(Collaboration $c, User $door, string $platform, string $url): void
    {
        $this->vereisCreator($c, $door);
        $this->vereisStatus($c, [Status::Goedgekeurd]);

        DB::transaction(function () use ($c, $door, $platform, $url) {
            $c->publications()->create(['platform' => $platform, 'url' => $url, 'posted_at' => now(), 'last_found_at' => now()]);
            $c->forceFill(['live_at' => now(), 'measure_until' => now()->addDays(config('mebranding.timers.measure_days'))]);
            $this->naar($c, Status::Live, $door, $url);

            $this->melder->stuur('o-live', $this->ondernemers($c), $this->vars($c), $this->urlOndernemer($c));

            $this->melder->stuur('c-live', $door, $this->vars($c), $this->urlCreator($c));

            // Vergoeding in geld (vanaf Platinum): opeisbaar in de eerstvolgende betaalrun.
            if ($c->fee_cents > 0) {
                $vrijdag = now()->isFriday() ? today() : today()->next('Friday');
                Payout::create(['creator_id' => $c->creator_id, 'collaboration_id' => $c->id, 'amount_cents' => $c->fee_cents, 'run_on' => $vrijdag]);
                $this->logboek($c, $door, 'vergoeding_opeisbaar', null, null, null, ['bedrag' => $c->fee_cents, 'betaalrun' => $vrijdag->toDateString()]);
                // De sjablonen zeggen zelf "vrijdag {datum}": de datum zonder weekdag, het bedrag zonder "gratis bezoek".
                $betaalrun = array_merge($this->vars($c), ['datum' => $vrijdag->translatedFormat('j F'), 'bedrag' => self::euro($c->fee_cents)]);
                $this->melder->stuur('c-uitbetaling-gepland', $door, $betaalrun, $this->urlCreator($c));
            }
        });
    }

    /* ----------------------------------------------------------------- 11. Afgerond (systeem, na de meetperiode) */

    public function afronden(Collaboration $c): void
    {
        $this->vereisStatus($c, [Status::Live]);
        $c->forceFill(['completed_at' => now()]);
        $this->naar($c, Status::Afgerond, null, 'Meetperiode van '.config('mebranding.timers.measure_days').' dagen voorbij');
        $this->melder->stuur('o-beoordelen', $this->eigenaren($c), $this->vars($c), $this->urlOndernemer($c));
    }

    /** Afronden van alle samenwerkingen waarvan de meetperiode voorbij is. Draait elk uur via de planner. */
    public function rondAf(): int
    {
        $klaar = Collaboration::where('status', Status::Live)->where('measure_until', '<=', now())->get();
        $klaar->each(fn ($c) => $this->afronden($c));

        return $klaar->count();
    }

    public function beoordelen(Collaboration $c, User $door, int $cijfer, ?string $tekst): void
    {
        $this->vereisStatus($c, [Status::Afgerond]);
        if ($c->review()->exists()) {
            throw ValidationException::withMessages(['rating' => 'Je hebt deze samenwerking al beoordeeld.']);
        }
        $c->review()->create(['user_id' => $door->id, 'rating' => $cijfer, 'body' => $tekst]);
        $this->logboek($c, $door, 'beoordeeld', null, null, null, ['cijfer' => $cijfer]);
        $vars = array_merge($this->vars($c), ['sterren' => $cijfer]);
        $this->melder->stuur('c-beoordeling', $c->creator->user, $vars, $this->urlCreator($c));
        if ($cijfer < (int) config('mebranding.drempels.oordeel_laag')) {
            $this->melder->stuur('t-oordeel-laag', $this->accountmanager($c)->merge($this->melder->admins()), $vars, $this->urlTeam($c));
        }
    }

    /* ----------------------------------------------------------------- Uitzonderingen (tegoed volgens CLAUDE.md 7.2, VOORSTEL) */

    public function annuleren(Collaboration $c, User $door, string $reden, bool $doorTeam): void
    {
        $this->vereisStatus($c, [Status::Nieuw, Status::CreatorZoeken, Status::CreatorGevonden, Status::BezoekGepland]);

        DB::transaction(function () use ($c, $door, $reden, $doorTeam) {
            $laat = ! $doorTeam && $c->visit_at && $c->visit_at->lt(now()->addHours(config('mebranding.timers.late_cancel_hours')));
            if ($laat) {
                $this->boek($c, CreditLedger::VERBRUIKEN, 0, 'Geannuleerd korter dan '.config('mebranding.timers.late_cancel_hours').' uur voor het bezoek: punten verbruikt', $door);
            } else {
                $this->boek($c, CreditLedger::VRIJGEVEN, $c->points, 'Geannuleerd: punten terug', $door);
            }

            $c->invitations()->whereNull('answer')->update(['answer' => 'ingetrokken', 'answered_at' => now()]);
            $c->forceFill(['cancel_reason' => $reden]);
            $this->naar($c, Status::Geannuleerd, $door, $reden, force: true);
            $this->werk->sluit($c);

            $tegoed = $laat ? 'Het tegoed is verbruikt, omdat de samenwerking kort voor het bezoek stopte.' : 'Je tegoed staat weer klaar.';
            $this->melder->stuur('o-gestopt', $this->eigenaren($c), array_merge($this->vars($c), ['tegoed' => $tegoed]), $this->urlOndernemer($c));
            $this->melder->stuur('c-gestopt', $c->creator?->user, $this->vars($c), $this->urlCreator($c));
        });
    }

    /** @param 'creator'|'ondernemer' $wie */
    public function nietVerschenen(Collaboration $c, User $door, string $wie): void
    {
        $this->vereisStatus($c, [Status::BezoekGepland]);

        DB::transaction(function () use ($c, $door, $wie) {
            if ($wie === 'ondernemer') {
                $this->boek($c, CreditLedger::VERBRUIKEN, 0, 'Niet verschenen: ondernemer was er niet, punten verbruikt', $door);
            } else {
                $this->boek($c, CreditLedger::VRIJGEVEN, $c->points, 'Niet verschenen: creator kwam niet, punten terug', $door);
            }
            $this->naar($c, Status::NietVerschenen, $door, $wie === 'creator' ? 'Creator kwam niet' : 'Ondernemer was er niet', force: true);
            $this->werk->sluit($c);

            if ($wie === 'creator') {
                $this->melder->stuur('o-niet-verschenen', $this->eigenaren($c), $this->vars($c), $this->urlOndernemer($c));
                $this->melder->stuur('c-niet-verschenen', $c->creator->user, $this->vars($c), $this->urlCreator($c));
            } else {
                $this->melder->stuur('o-zaak-afwezig', $this->eigenaren($c), $this->vars($c), $this->urlOndernemer($c));
                $this->melder->stuur('c-zaak-afwezig', $c->creator->user, $this->vars($c), $this->urlCreator($c));
            }
            $this->melder->stuur('t-niet-verschenen', $this->accountmanager($c), array_merge($this->vars($c), [
                'toelichting' => $wie === 'creator' ? 'de creator kwam niet, het tegoed is teruggezet' : 'de zaak was er niet, het tegoed is verbruikt',
            ]), $this->urlTeam($c));
        });
    }

    /* ----------------------------------------------------------------- Intern */

    private function naar(Collaboration $c, Status $naar, ?User $door, ?string $reden = null, bool $force = false): void
    {
        $van = $c->status;
        if (! $force && ! in_array($naar->value, self::OVERGANGEN[$van->value] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Van {$van->label()} naar {$naar->label()} kan niet."]);
        }

        $c->status = $naar;
        $c->save();
        $this->logboek($c, $door, 'status', $van, $naar, $reden);
        DB::afterCommit(fn () => SamenwerkingGewijzigd::dispatch($c, $van, $naar, $door));
    }

    private function vereisStatus(Collaboration $c, array $statussen): void
    {
        if (! in_array($c->status, $statussen, true)) {
            throw ValidationException::withMessages(['status' => "Dit kan niet in status {$c->status->label()}."]);
        }
    }

    private function vereisCreator(Collaboration $c, User $door): void
    {
        if ($c->creator?->user_id !== $door->id) {
            abort(403);
        }
    }

    private function boek(Collaboration $c, string $soort, int $punten, string $reden, ?User $door): void
    {
        CreditLedger::create([
            'company_id' => $c->company_id, 'collaboration_id' => $c->id, 'kind' => $soort,
            'points' => $punten, 'reason' => $reden, 'user_id' => $door?->id,
        ]);
    }

    private function logboek(Collaboration $c, ?User $door, string $event, ?Status $van = null, ?Status $naar = null, ?string $reden = null, ?array $data = null): void
    {
        ActivityLog::create([
            'user_id' => $door?->id, 'subject_type' => $c->getMorphClass(), 'subject_id' => $c->id, 'event' => $event,
            'from_status' => $van?->value, 'to_status' => $naar?->value, 'reason' => $reden, 'data' => $data,
        ]);
    }

    /* ----------------------------------------------------------------- Ontvangers, variabelen en links (ook voor App\Services\Herinneringen) */

    /** Eigenaar en leden van het bedrijf. */
    public function ondernemers(Collaboration $c)
    {
        return $c->company->users()->get();
    }

    /** Alleen de eigenaren, voor sjablonen met ontvanger "Eigenaar". */
    public function eigenaren(Collaboration $c)
    {
        return $c->company->owners()->get();
    }

    /** De accountmanager van het bedrijf; zonder accountmanager het team dat koppelt. */
    public function accountmanager(Collaboration $c)
    {
        return $c->company->accountManager ? collect([$c->company->accountManager]) : $this->melder->team('samenwerkingen.koppelen');
    }

    /** Geldbedrag voor in een bericht: "€ 50" of "€ 12,50". */
    public static function euro(int $centen): string
    {
        return '€ '.number_format($centen / 100, $centen % 100 ? 2 : 0, ',', '.');
    }

    /** Variabelen voor de berichtsjablonen. */
    public function vars(Collaboration $c, ?Creator $creator = null): array
    {
        $creator ??= $c->creator;
        $fee = $creator ? $creator->feeCents() : $c->fee_cents;

        return [
            'zaak' => $c->company->name,
            'vestiging' => $c->location->name,
            'plaats' => $c->location->city ?? $c->location->name,
            'samenwerking' => $c->title,
            'nummer' => $c->number,
            'creator' => $creator?->user->name ?? 'de creator',
            'datum' => $c->visit_at?->translatedFormat('l j F') ?? '',
            'tijd' => $c->visit_at?->format('H:i') ?? '',
            // Bronze, Silver en Gold: geen geld, wel een gratis bezoek (besloten 18-11-2026).
            'bedrag' => $fee > 0 ? 'gratis bezoek en € '.number_format($fee / 100, 0, ',', '.') : 'gratis bezoek (geen geldbedrag)',
        ];
    }

    public function urlOndernemer(Collaboration $c): string
    {
        return route('ondernemer.samenwerking', $c);
    }

    public function urlCreator(Collaboration $c): string
    {
        return route('creator.klus', $c);
    }

    public function urlTeam(Collaboration $c): string
    {
        return route('team.samenwerking', $c);
    }
}
