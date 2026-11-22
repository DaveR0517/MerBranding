<?php

namespace App\Listeners;

use App\Events\SamenwerkingGewijzigd;
use App\Models\ActivityLog;
use App\Models\ScoreEvent;
use App\Models\Status;
use App\Services\Score;

/**
 * Score bijwerken na statuswijzigingen van de keten (docs/02 flow 5).
 * - Afgerond: score opnieuw uitrekenen.
 * - Niet verschenen omdat de creator niet kwam: strafpunt op Betrouwbaarheid. Was de ondernemer er niet: geen gevolg.
 * - Later aangeleverd dan de deadline of later geplaatst dan de afgesproken datum: strafpunt te laat.
 * Weigeren of laten verlopen van een uitnodiging verandert geen status van deze creator en telt dus nooit mee.
 */
class WerkScoreBij
{
    public function __construct(private Score $score) {}

    public function handle(SamenwerkingGewijzigd $e): void
    {
        $c = $e->samenwerking;
        if (! $c->creator_id) {
            return;
        }

        match ($e->naar) {
            Status::Afgerond => $this->score->bereken($c->creator),
            Status::NietVerschenen => $this->nietVerschenen($e),
            Status::TerControle => $e->van === Status::ContentMaken && $c->content_deadline_at?->isPast()
                ? $this->score->registreer($c, ScoreEvent::TE_LAAT, 'Video later aangeleverd dan de deadline', $e->door)
                : null,
            Status::Live => $c->publish_on && $c->live_at && $c->live_at->startOfDay()->gt($c->publish_on)
                ? $this->score->registreer($c, ScoreEvent::TE_LAAT, 'Later geplaatst dan de afgesproken datum', $e->door)
                : null,
            default => null,
        };
    }

    private function nietVerschenen(SamenwerkingGewijzigd $e): void
    {
        // De keten legt in het logboek vast wie er niet was (App\Services\Samenwerking::nietVerschenen).
        $reden = ActivityLog::where('subject_type', $e->samenwerking->getMorphClass())->where('subject_id', $e->samenwerking->id)
            ->where('to_status', Status::NietVerschenen->value)->latest('id')->value('reason');

        if ($reden === Score::REDEN_CREATOR_KWAM_NIET) {
            $this->score->registreer($e->samenwerking, ScoreEvent::NIET_GEKOMEN, 'Niet verschenen na toezegging', $e->door);
        }
    }
}
