<?php

namespace Modules\Attendance\app\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Company\app\Concerns\BelongsToCompany;

/**
 * A company holiday. Auto-applied to the Attendance Register PDF: every holiday
 * that falls in the report month shows as HD on every employee's sheet.
 */
class Holiday extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'holiday_date', 'name'];

    protected $casts = [
        'holiday_date' => 'date',
    ];

    /** Holidays inside one calendar month, earliest first. */
    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();

        return $query
            ->whereBetween('holiday_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->orderBy('holiday_date');
    }

    /**
     * Holiday names for a month keyed by day-of-month (1..31) — ready for the
     * register to look up `$holidays->get($day)`.
     *
     * @return Collection<int, string>
     */
    public static function datesForMonth(int $year, int $month): Collection
    {
        return static::query()
            ->forMonth($year, $month)
            ->get()
            ->mapWithKeys(fn (Holiday $h) => [(int) $h->holiday_date->day => $h->name]);
    }
}
