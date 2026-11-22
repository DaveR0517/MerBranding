<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** De persoonlijke link waarmee een creator of ondernemer iemand uitnodigt. */
class Uitnodigingslink extends Model
{
    /** Naam van de cookie die /uitnodiging/{code} zet; LeadController leest hem bij een aanmelding. */
    public const COOKIE = 'mb_uitnodiging';

    protected $table = 'referrals';

    protected $fillable = ['user_id', 'code'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** De link van deze gebruiker; bij het eerste gebruik gemaakt, op basis van de voornaam. */
    public static function voor(User $user): self
    {
        return self::firstOrCreate(['user_id' => $user->id], ['code' => self::nieuweCode($user)]);
    }

    private static function nieuweCode(User $user): string
    {
        $basis = Str::slug(Str::before($user->name, ' ')) ?: 'mb';
        do {
            $code = $basis.'-'.Str::lower(Str::random(5));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /** Aanmeldingen die via deze link binnenkwamen (LeadController zet de code in leads.data.referral_code). */
    public function aanmeldingen()
    {
        return Lead::where('data->referral_code', $this->code);
    }
}
