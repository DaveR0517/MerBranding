<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Het eigen profiel van een creator, naast de gegevens in creators: foto, bio, werkgebied en portfolio.
 * Ook de plek voor de regels rond uitbetaalgegevens en de raamovereenkomst van deze module.
 */
class CreatorProfiel extends Model
{
    protected $table = 'creator_profiles';

    protected $fillable = ['creator_id', 'photo_path', 'bio', 'radius_km', 'portfolio_links'];

    /** Niches zoals op de website (formulier voor creators). Opgeslagen als label in creators.niches. */
    public const NICHES = [
        'eten' => 'Eten en drinken',
        'lifestyle' => 'Lifestyle',
        'uitjes' => 'Uitjes en cultuur',
        'reizen' => 'Reizen en overnachten',
        'mode' => 'Mode en winkelen',
        'gezin' => 'Gezin',
        'anders' => 'Anders',
    ];

    public const SOORTEN = ['influencer' => 'Influencer', 'ugc' => 'UGC-maker', 'beide' => 'Influencer en UGC-maker'];

    /** Btw-status, de creator kiest zelf (CLAUDE.md 7.1). */
    public const BTW = ['btw' => 'Btw-plichtig', 'kor' => 'KOR', 'geen' => 'Geen onderneming'];

    public const RAAMOVEREENKOMST = 'raamovereenkomst';

    protected function casts(): array
    {
        return ['portfolio_links' => 'array'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public static function van(Creator $creator): self
    {
        return self::firstOrNew(['creator_id' => $creator->id]);
    }

    public function fotoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /** Versie van de raamovereenkomst (config mebranding.framework_version). Een nieuwe versie vraagt een nieuwe handtekening. */
    public static function raamVersie(): string
    {
        return (string) config('mebranding.framework_version');
    }

    public static function raamGetekend(User $user): ?Signature
    {
        return Signature::where('user_id', $user->id)->where('agreement', self::RAAMOVEREENKOMST)
            ->where('version', self::raamVersie())->latest()->first();
    }

    /**
     * Uitbetaalgegevens uit het versleutelde veld creators.payout_details (JSON).
     *
     * @return array{iban?: string, name?: string, vat_number?: string}
     */
    public static function uitbetaling(Creator $creator): array
    {
        $json = $creator->payout_details;

        return is_string($json) ? (json_decode($json, true) ?: []) : [];
    }

    /** Compleet voor een uitbetaling: IBAN, tenaamstelling en een btw-status. */
    public static function uitbetalingCompleet(Creator $creator): bool
    {
        $gegevens = self::uitbetaling($creator);

        return filled($gegevens['iban'] ?? null) && filled($gegevens['name'] ?? null) && filled($creator->vat_status);
    }

    /** Toon alleen het begin en het eind van een IBAN. */
    public static function maskeer(?string $iban): ?string
    {
        if (! $iban) {
            return null;
        }

        return substr($iban, 0, 4).' •••• •••• '.substr($iban, -2);
    }

    /** IBAN-controle met het modulo 97-getal. */
    public static function ibanGeldig(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            return false;
        }
        $omgezet = '';
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $teken) {
            $omgezet .= ctype_alpha($teken) ? (string) (ord($teken) - 55) : $teken;
        }
        $rest = 0;
        foreach (str_split($omgezet, 7) as $deel) {
            $rest = (int) ($rest.$deel) % 97;
        }

        return $rest === 1;
    }
}
