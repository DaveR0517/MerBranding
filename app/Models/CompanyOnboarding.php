<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** De drie startstappen van een bedrijf (welkomstscherm "Aan de slag"). */
class CompanyOnboarding extends Model
{
    protected $table = 'company_onboarding';

    /** Stap in de url => kolom. */
    public const STAPPEN = ['vestiging' => 'locations_checked_at', 'team' => 'team_invited_at', 'aanvraag' => 'first_request_at'];

    protected $fillable = ['company_id', 'locations_checked_at', 'team_invited_at', 'first_request_at', 'closed_at'];

    protected function casts(): array
    {
        return ['locations_checked_at' => 'datetime', 'team_invited_at' => 'datetime', 'first_request_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public static function voor(Company $bedrijf): self
    {
        return self::firstOrCreate(['company_id' => $bedrijf->id]);
    }

    public static function vink(Company $bedrijf, string $stap): void
    {
        $o = self::voor($bedrijf);
        $kolom = self::STAPPEN[$stap];
        if (! $o->{$kolom}) {
            $o->update([$kolom => now()]);
        }
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Stand per stap. Een stap telt ook als gedaan als het platform het al ziet:
     * meer dan één gebruiker, of al een samenwerking.
     *
     * @return array<string, bool>
     */
    public function stand(): array
    {
        $bedrijf = $this->company;

        return [
            'vestiging' => (bool) $this->locations_checked_at,
            'team' => (bool) $this->team_invited_at || $bedrijf->users()->count() > 1,
            'aanvraag' => (bool) $this->first_request_at || $bedrijf->collaborations()->exists(),
        ];
    }

    /** Het welkomstscherm verdwijnt als alles is afgevinkt of als de ondernemer het sluit. */
    public function klaar(): bool
    {
        return $this->closed_at !== null || ! in_array(false, $this->stand(), true);
    }
}
