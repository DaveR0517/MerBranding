<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot('role')->withTimestamps();
    }

    public function pushAbonnementen(): HasMany
    {
        return $this->hasMany(PushAbonnement::class);
    }

    public function creator(): HasOne
    {
        return $this->hasOne(Creator::class);
    }

    /** Teamleden: Super Admin of een van de rechtensjablonen. */
    public function isTeam(): bool
    {
        return $this->roles->contains(fn ($r) => ! in_array($r->name, ['Ondernemer Eigenaar', 'Ondernemer Lid', 'Creator', 'Student'], true));
    }

    /** Omgevingen in volgorde van voorrang, met hun startpagina. Eén bron voor toegang, menu en wissel. */
    public const OMGEVINGEN = [
        'team' => 'team.werkvoorraad',
        'creator' => 'creator.overzicht',
        'ondernemer' => 'ondernemer.overzicht',
        'academy' => 'academy.overzicht',
    ];

    /** Mag deze gebruiker in deze omgeving (routes met middleware AlleenOmgeving)? */
    public function magOmgeving(string $omgeving): bool
    {
        return match ($omgeving) {
            'team' => $this->isTeam(),
            'creator' => $this->creator()->exists(),
            'ondernemer' => $this->companies()->exists(),
            // Creators volgen de academy vanuit hun eigen omgeving (menu Mijn academy).
            'academy' => $this->hasRole('Student') || $this->creator()->exists(),
            default => false,
        };
    }

    /**
     * Omgevingen waartussen deze gebruiker wisselt, bijvoorbeeld een teamlid met een eigen zaak (team en ondernemer).
     * De academy telt voor een creator niet als aparte omgeving.
     *
     * @return list<string>
     */
    public function omgevingen(): array
    {
        $creator = $this->creator()->exists();

        return array_values(array_filter(array_keys(self::OMGEVINGEN), fn ($o) => $this->magOmgeving($o) && ! ($o === 'academy' && $creator)));
    }

    /** Het portaal waar deze gebruiker na inloggen terechtkomt. */
    public function homePortal(): string
    {
        return $this->omgevingen()[0] ?? 'geen';
    }
}
