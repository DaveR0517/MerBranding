<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Bericht;
use App\Models\BerichtGelezen;
use App\Models\Collaboration;
use App\Models\User;
use App\Notifications\NieuwBericht;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Berichten per samenwerking. Standaard twee kanalen: ondernemer met het team en creator met het team.
 * Het gezamenlijke kanaal (ondernemer, creator en team) bestaat alleen met de instelling
 * mebranding.berichten.ondernemer_naar_creator aan. Open punt (CLAUDE.md 13.10): VOORSTEL uit.
 */
class Berichten
{
    public function __construct(private Melder $melder) {}

    public static function gezamenlijkAan(): bool
    {
        return (bool) config('mebranding.berichten.ondernemer_naar_creator', false);
    }

    /** @return list<string> kanalen die deze omgeving ziet en waarin ze kan schrijven */
    public static function kanalen(string $omgeving): array
    {
        $kanalen = match ($omgeving) {
            'team' => [Bericht::TEAM_ONDERNEMER, Bericht::TEAM_CREATOR],
            'ondernemer' => [Bericht::TEAM_ONDERNEMER],
            'creator' => [Bericht::TEAM_CREATOR],
            default => [],
        };

        return self::gezamenlijkAan() && $kanalen ? [...$kanalen, Bericht::ALLE] : $kanalen;
    }

    /** Naam van een kanaal zoals de lezer het ziet. */
    public static function kanaalNaam(string $kanaal, string $omgeving): string
    {
        return match ([$kanaal, $omgeving]) {
            [Bericht::TEAM_ONDERNEMER, 'team'] => 'Met de ondernemer',
            [Bericht::TEAM_CREATOR, 'team'] => 'Met de creator',
            [Bericht::ALLE, 'team'], [Bericht::ALLE, 'ondernemer'], [Bericht::ALLE, 'creator'] => 'Met iedereen',
            default => 'Met het team',
        };
    }

    /** Wie het kanaal kan lezen, buiten de schrijver: zo weet de schrijver wie meeleest. */
    public static function zichtbaarVoor(string $kanaal, string $omgeving): string
    {
        return match ($kanaal) {
            Bericht::TEAM_ONDERNEMER => $omgeving === 'team' ? 'Zichtbaar voor de ondernemer en het team.' : 'Zichtbaar voor jou en het team van MEBRANDING. De creator ziet dit niet.',
            Bericht::TEAM_CREATOR => $omgeving === 'team' ? 'Zichtbaar voor de creator en het team.' : 'Zichtbaar voor jou en het team van MEBRANDING. De ondernemer ziet dit niet.',
            default => 'Zichtbaar voor de ondernemer, de creator en het team van MEBRANDING. Deel geen telefoonnummers.',
        };
    }

    public function sturen(Collaboration $c, User $afzender, string $omgeving, string $kanaal, string $tekst): Bericht
    {
        abort_unless(in_array($kanaal, self::kanalen($omgeving), true), 403, 'Dit gesprek is niet beschikbaar.');
        abort_if($kanaal !== Bericht::TEAM_ONDERNEMER && ! $c->creator_id, 422, 'Er is nog geen creator gekoppeld.');

        $bericht = Bericht::create(['collaboration_id' => $c->id, 'user_id' => $afzender->id, 'visible_to' => $kanaal, 'body' => $tekst]);
        $this->gelezen($c, $afzender);
        ActivityLog::create(['user_id' => $afzender->id, 'subject_type' => $c->getMorphClass(), 'subject_id' => $c->id, 'event' => 'bericht', 'data' => ['kanaal' => $kanaal, 'bericht_id' => $bericht->id]]);

        $this->meld($c, $afzender, $omgeving, $kanaal, $tekst);

        return $bericht;
    }

    /** Berichten die deze omgeving ziet, oudste eerst. */
    public function gesprek(Collaboration $c, string $omgeving): Collection
    {
        return Bericht::with('user')->where('collaboration_id', $c->id)->whereIn('visible_to', self::kanalen($omgeving))->oldest()->oldest('id')->get();
    }

    public function gelezen(Collaboration $c, User $user): void
    {
        BerichtGelezen::updateOrCreate(['collaboration_id' => $c->id, 'user_id' => $user->id], ['read_at' => now()]);
    }

    /**
     * Aantal ongelezen berichten per samenwerking voor deze gebruiker.
     *
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    public function ongelezen(array $ids, User $user, string $omgeving): array
    {
        if ($ids === []) {
            return [];
        }
        $gelezen = BerichtGelezen::where('user_id', $user->id)->whereIn('collaboration_id', $ids)->pluck('read_at', 'collaboration_id');

        return Bericht::whereIn('collaboration_id', $ids)->whereIn('visible_to', self::kanalen($omgeving))
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $user->id))
            ->get(['collaboration_id', 'created_at'])
            ->groupBy('collaboration_id')
            ->map(fn ($rijen, $id) => $rijen->filter(fn ($b) => ! isset($gelezen[$id]) || $b->created_at->gt($gelezen[$id]))->count())
            ->all();
    }

    /** Wie een melding krijgt: de deelnemers van het kanaal, behalve de kant van de afzender. */
    private function meld(Collaboration $c, User $afzender, string $omgeving, string $kanaal, string $tekst): void
    {
        $c->loadMissing(['company.owners', 'creator.user']);
        $titel = $c->title;
        $kort = Str::limit($tekst, 140);
        $kanten = match ($kanaal) {
            Bericht::TEAM_ONDERNEMER => ['team', 'ondernemer'],
            Bericht::TEAM_CREATOR => ['team', 'creator'],
            default => ['team', 'ondernemer', 'creator'],
        };

        foreach (array_diff($kanten, [$omgeving]) as $kant) {
            $ontvangers = $this->deelnemers($c, $kant)->reject(fn (User $u) => $u->id === $afzender->id);
            if ($ontvangers->isEmpty()) {
                continue;
            }
            $url = route("{$kant}.communicatie.berichten.gesprek", $c);

            if ($kant === 'ondernemer' && $omgeving === 'team') {
                // Vraag van het team aan de ondernemer: het bestaande sjabloon (in de app en per e-mail).
                $this->melder->stuur('o-vraag', $ontvangers, ['am' => $afzender->name, 'samenwerking' => $titel, 'vraag' => $kort], $url);

                continue;
            }

            Notification::send($ontvangers, new NieuwBericht("Nieuw bericht over {$titel}", "{$afzender->name}: {$kort}", $url));
        }
    }

    /** @return Collection<int, User> */
    private function deelnemers(Collaboration $c, string $kant): Collection
    {
        return match ($kant) {
            'ondernemer' => $c->company->owners->merge(array_filter([$c->requested_by ? User::find($c->requested_by) : null]))->unique('id')->values(),
            'creator' => collect([$c->creator?->user])->filter()->values(),
            default => $c->company->account_manager_id
                ? collect([User::find($c->company->account_manager_id)])->filter()->values()
                : $this->melder->team('berichten.beheren'),
        };
    }
}
