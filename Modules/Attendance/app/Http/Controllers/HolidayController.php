<?php

namespace Modules\Attendance\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Attendance\app\Models\Holiday;

/**
 * HR-side company holiday master (role = instructor). Holidays entered here are
 * pulled into the Attendance Register PDF automatically for their month.
 */
class HolidayController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->input('year', now()->year);

        $holidays = Holiday::whereBetween('holiday_date', [
            Carbon::create($year, 1, 1)->toDateString(),
            Carbon::create($year, 12, 31)->toDateString(),
        ])->orderBy('holiday_date')->get();

        return view('attendance::holidays', [
            'year'     => $year,
            'holidays' => $holidays,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'holiday_date' => ['required', 'date'],
            'name'         => ['required', 'string', 'max:120'],
        ]);

        $date = Carbon::parse($data['holiday_date'])->toDateString();

        $exists = Holiday::whereDate('holiday_date', $date)->exists();
        if ($exists) {
            return back()->with('error', 'A holiday is already set for '.Carbon::parse($date)->format('d M Y').'.');
        }

        Holiday::create(['holiday_date' => $date, 'name' => $data['name']]);

        return redirect()
            ->route('hr.attendance.holidays.index', ['year' => Carbon::parse($date)->year])
            ->with('success', 'Holiday added.');
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        $year = $holiday->holiday_date->year;
        $holiday->delete();

        return redirect()
            ->route('hr.attendance.holidays.index', ['year' => $year])
            ->with('success', 'Holiday removed.');
    }
}
