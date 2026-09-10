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
     * Optional day-type tag layered on top of the status code. Null is an
     * ordinary worked-or-absent day. EL/CL/SL/OD/WO are fully paid — they never
     * cause loss of pay whichever halves are marked absent; H is a half day
     * (0.5 loss of pay). NB: the tag is "H" — "HD" is the Holiday marker on the
     * Attendance Register, a different concept.
     */
    public const DAY_EL = 'EL';
    public const DAY_CL = 'CL';
    public const DAY_HD = 'H';
    public const DAY_WO = 'WO';
    public const DAY_OD = 'OD';
    public const DAY_SL = 'SL';

    public const DAY_TYPES = [
        self::DAY_EL, self::DAY_CL, self::DAY_HD, self::DAY_WO, self::DAY_OD, self::DAY_SL,
    ];

    public const DAY_TYPE_LABELS = [
        self::DAY_EL => 'Earned Leave (EL)',
        self::DAY_CL => 'Casual Leave (CL)',
        self::DAY_HD => 'Half Day (H)',
        self::DAY_WO => 'Week Off (WO)',
        self::DAY_OD => 'On Duty (OD)',
        self::DAY_SL => 'Sick Leave (SL)',
    ];

    /** Day types whose absent halves are still fully paid — no loss of pay. */
    public const PAID_DAY_TYPES = [self::DAY_EL, self::DAY_CL, self::DAY_SL, self::DAY_OD, self::DAY_WO];

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

    /** This day is fully paid despite any absent half (EL/CL/SL/OD/WO). */
    public function isPaidDayType(): bool
    {
        return in_array($this->day_type, self::PAID_DAY_TYPES, true);
    }

    /** A leave day — carries a leave-head tag (EL/CL/SL/OD) or came from an approved leave. */
    public function isLeave(): bool
    {
        return in_array($this->day_type, [self::DAY_EL, self::DAY_CL, self::DAY_SL, self::DAY_OD], true)
            || $this->source === 'leave';
    }

    /**
     * A half-day taken as leave — a First Half / Second Half leave request. Both
     * count and display as Leave on the Attendance Register.
     */
    public function isHalfDayLeave(): bool
    {
        return $this->isLeave() && in_array($this->status, [self::AP, self::PA], true);
    }

    /**
     * Loss-of-pay weight: a half day (H) is always 0.5; a fully-paid day type
     * is 0; otherwise the portion of the day scored absent by the status code.
     */
    public function lopWeight(): float
    {
        if ($this->day_type === self::DAY_HD) {
            return 0.5;
        }

        return $this->isPaidDayType() ? 0.0 : $this->absentPortion();
    }

    /** Human label for the day — the day-type name if tagged, else the status. */
    public function label(): string
    {
        return self::DAY_TYPE_LABELS[$this->day_type]
            ?? (self::STATUS_LABELS[$this->status] ?? (string) $this->status);
    }

    /**
     * Compact code for the attendance register: the day-type tag (EL/CL/H/WO/
     * OD/SL) when set, otherwise the raw PP/AP/PA/AA status code.
     */
    public function shortCode(): string
    {
        return $this->day_type !== null ? (string) $this->day_type : (string) $this->status;
    }

    /** CSS modifier for the .pv-badge chip — keyed on day type, else status. */
    public function badgeClass(): string
    {
        return match ($this->day_type) {
            self::DAY_HD => 'halfday',
            self::DAY_WO => 'holiday',
            self::DAY_EL, self::DAY_CL, self::DAY_SL, self::DAY_OD => 'leave',
            default      => strtolower((string) $this->status),
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
            'WFH'     => [self::PP, null],
            'Holiday' => [self::AA, self::DAY_WO],
            'Leave'   => [self::AA, self::DAY_CL],
            'HalfDay' => [self::PA, null],
            'Absent'  => [self::AA, null],
            default   => [self::PP, null],
        };
    }
}
