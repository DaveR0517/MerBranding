<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Een live cursusdag van de academy. "Vol" volgt uit het aantal plekken, niet uit de status. */
class CourseDay extends Model
{
    public const OPEN = 'open';

    public const AFGESLOTEN = 'afgesloten';

    public const GEANNULEERD = 'geannuleerd';

    protected $fillable = ['title', 'date', 'starts_at', 'ends_at', 'location_name', 'address', 'city', 'max_participants', 'teacher_name', 'price_cents', 'exam_required', 'status', 'closed_at', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date', 'exam_required' => 'boolean', 'closed_at' => 'datetime', 'max_participants' => 'integer', 'price_cents' => 'integer'];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Inschrijvingen die een plek innemen. */
    public function bezet(): int
    {
        return $this->enrollments()->whereIn('status', Enrollment::MET_PLEK)->count();
    }

    public function vrij(): int
    {
        return max(0, $this->max_participants - $this->bezet());
    }

    /** @return Collection<int, Enrollment> */
    public function wachtlijst()
    {
        return $this->enrollments()->where('status', Enrollment::WACHTLIJST)->orderBy('created_at')->orderBy('id')->with('user')->get();
    }

    public function isVandaag(): bool
    {
        return $this->date->isSameDay(Carbon::now(config('app.timezone')));
    }

    public function start(): Carbon
    {
        return $this->date->copy()->setTimeFromTimeString($this->starts_at);
    }

    /** Leesbare naam voor berichten: "Basis: video maken in Harlingen". */
    public function naam(): string
    {
        return "{$this->title} in {$this->city}";
    }

    public function datumTekst(): string
    {
        return $this->date->translatedFormat('D j M');
    }

    public function tijden(): string
    {
        return substr($this->starts_at, 0, 5).' - '.substr($this->ends_at, 0, 5);
    }
}
