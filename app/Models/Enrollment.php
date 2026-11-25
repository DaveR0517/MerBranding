<?php

namespace App\Models;

use App\Models\Concerns\HeeftHerinneringen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Inschrijving van een student voor een cursusdag, met wachtlijst, check-in en betaalstatus (placeholder). */
class Enrollment extends Model
{
    use HeeftHerinneringen;

    public const INGESCHREVEN = 'ingeschreven';

    public const WACHTLIJST = 'wachtlijst';

    public const AANWEZIG = 'aanwezig';

    public const AFWEZIG = 'afwezig';

    public const GESLAAGD = 'geslaagd';

    public const AFGEMELD = 'afgemeld';

    /** Statussen die een plek op de dag innemen. */
    public const MET_PLEK = [self::INGESCHREVEN, self::AANWEZIG, self::AFWEZIG, self::GESLAAGD];

    public const NOG_TE_BETALEN = 'nog_te_betalen';

    public const BETAALD = 'betaald';

    protected $fillable = ['course_day_id', 'user_id', 'status', 'paid_status', 'paid_at', 'offer_expires_at', 'checkin_code', 'checked_in_at', 'checked_in_by', 'checkin_reason', 'phone', 'city', 'tiktok', 'instagram', 'birthdate'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'offer_expires_at' => 'datetime', 'checked_in_at' => 'datetime', 'birthdate' => 'date'];
    }

    public function courseDay(): BelongsTo
    {
        return $this->belongsTo(CourseDay::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): HasOne
    {
        return $this->hasOne(Exam::class);
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    /** Plek op de wachtlijst (1 is de eerste), of null. */
    public function plek(): ?int
    {
        if ($this->status !== self::WACHTLIJST) {
            return null;
        }

        return $this->courseDay->wachtlijst()->search(fn (Enrollment $e) => $e->id === $this->id) + 1;
    }
}
