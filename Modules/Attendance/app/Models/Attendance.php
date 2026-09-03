<?php

namespace Modules\Attendance\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Company\app\Concerns\BelongsToCompany;

class Attendance extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    /**
     * Canonical per-day status codes. Each day is scored as two halves —
     * the first letter is the morning, the second the afternoon; P = present,
     * A = off/absent:
     *   PP  full day present
     *   AP  first half off/absent, present in the second half
     *   PA  present in the first half, second half off/absent
     *   AA  full day absent/off
     */
    public const PP = 'PP';
    public const AP = 'AP';
    public const PA = 'PA';
    public const AA = 'AA';

    public const STATUSES = [self::PP, self::AP, self::PA, self::AA];

    public const STATUS_LABELS = [
        self::PP => 'Present — full day (PP)',
        self::AP => 'First half off (AP)',
        self::PA => 'Second half off (PA)',
        self::AA => 'Absent — full day (AA)',
    ];

    /**
     * Optional pay-treatment / reason tag layered on top of the status code.
     * Null is an ordinary worked-or-absent day. The "paid" types never cause
     * loss of pay whichever halves are marked absent.
     */
    public const DAY_WFH          = 'wfh';
    public const DAY_HOLIDAY      = 'holiday';
    public const DAY_PAID_LEAVE   = 'paid_leave';
    public const DAY_UNPAID_LEAVE = 'unpaid_leave';

    public const DAY_TYPES = [
        self::DAY_WFH, self::DAY_HOLIDAY, self::DAY_PAID_LEAVE, self::DAY_UNPAID_LEAVE,
    ];

    public const DAY_TYPE_LABELS = [
        self::DAY_WFH          => 'Work from home',
        self::DAY_HOLIDAY      => 'Holiday',
        self::DAY_PAID_LEAVE   => 'Paid leave',
        self::DAY_UNPAID_LEAVE => 'Unpaid leave',
    ];

    /** Day types whose absent halves are still paid — no loss of pay. */
    public const PAID_DAY_TYPES = [self::DAY_WFH, self::DAY_HOLIDAY, self::DAY_PAID_LEAVE];

    protected $fillable = [
        'user_id', 'attendance_date', 'status', 'day_type', 'check_in', 'check_out',
        'worked_minutes', 'source', 'marked_by', 'remarks',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /** Fraction of the day scored absent: PP → 0, AP/PA → 0.5, AA → 1. */
    public function absentPortion(): float
    {
        return match ($this->status) {
            self::AP, self::PA => 0.5,
            self::AA           => 1.0,
            default            => 0.0,
        };
    }

    /** The employee worked at least one half of the day. */
    public function isPresentAny(): bool
    {
        return $this->status !== self::AA;
    }

    /** This day is paid despite any absent half (WFH / holiday / paid leave). */
    public function isPaidDayType(): bool
    {
        return in_array($this->day_type, self::PAID_DAY_TYPES, true);
    }

    /** Loss-of-pay weight: 0 for a paid day type, otherwise the absent portion. */
    public function lopWeight(): float
    {
        return $this->isPaidDayType() ? 0.0 : $this->absentPortion();
    }

    /** Human label for the day — the day-type name if tagged, else the status. */
    public function label(): string
    {
        return self::DAY_TYPE_LABELS[$this->day_type]
            ?? (self::STATUS_LABELS[$this->status] ?? (string) $this->status);
    }

    /**
     * Compact code for the attendance register: 'H' for a holiday, 'WFH' for a
     * work-from-home day, otherwise the raw PP/AP/PA/AA status code.
     */
    public function shortCode(): string
    {
        return match ($this->day_type) {
            self::DAY_HOLIDAY => 'H',
            self::DAY_WFH     => 'WFH',
            default           => (string) $this->status,
        };
    }

    /** CSS modifier for the .pv-badge chip — keyed on day type, else status. */
    public function badgeClass(): string
    {
        return match ($this->day_type) {
            self::DAY_HOLIDAY => 'holiday',
            self::DAY_WFH     => 'wfh',
            self::DAY_PAID_LEAVE, self::DAY_UNPAID_LEAVE => 'leave',
            default           => strtolower((string) $this->status),
        };
    }

    /**
     * Map a pre-conversion status value to the new [status, day_type] pair.
     * Used by the status-codes migration to backfill existing rows.
     *
     * @return array{0: string, 1: string|null}
     */
    public static function legacyStatusMap(string $legacy): array
    {
        return match ($legacy) {
            'Present' => [self::PP, null],
            'WFH'     => [self::PP, self::DAY_WFH],
            'Holiday' => [self::AA, self::DAY_HOLIDAY],
            'Leave'   => [self::AA, self::DAY_PAID_LEAVE],
            'HalfDay' => [self::PA, null],
            'Absent'  => [self::AA, null],
            default   => [self::PP, null],
        };
    }
}
