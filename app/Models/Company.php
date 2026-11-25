<?php

namespace App\Models;

use App\Models\Concerns\HeeftHerinneringen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HeeftHerinneringen;

    protected $fillable = ['name', 'kvk', 'branche', 'email', 'phone', 'account_manager_id', 'self_confirm_visits', 'owner_approves_content'];

    protected function casts(): array
    {
        return ['self_confirm_visits' => 'boolean', 'owner_approves_content' => 'boolean', 'onbeperkt_tegoed' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'eigenaar');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function collaborations(): HasMany
    {
        return $this->hasMany(Collaboration::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(CreditLedger::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    /** Beschikbaar tegoed: som van alle boekingen in het grootboek. */
    /** Genoeg tegoed voor een aanvraag van zoveel punten? Een eigen zaak met onbeperkt tegoed altijd. */
    public function kanBetalen(int $punten): bool
    {
        return $this->onbeperkt_tegoed || $this->availablePoints() >= $punten;
    }

    /** Tegoed voor de schermen: saldo en of het onbeperkt is. */
    public function tegoed(): array
    {
        return ['available_points' => $this->availablePoints(), 'unlimited_credit' => $this->onbeperkt_tegoed];
    }

    public function availablePoints(): int
    {
        return (int) $this->ledger()->sum('points');
    }

    /** Punten die nu vastliggen in lopende aanvragen. */
    public function reservedPoints(): int
    {
        return (int) $this->collaborations()
            ->whereIn('status', [Status::Nieuw, Status::CreatorZoeken, Status::CreatorGevonden, Status::BezoekGepland])
            ->sum('points');
    }
}
