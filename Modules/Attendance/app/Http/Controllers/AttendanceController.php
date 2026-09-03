<?php

namespace Modules\Attendance\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SheetExporter;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\Attendance\app\Models\Attendance;
use Modules\Attendance\app\Services\AttendanceService;
use Modules\HrEmployee\app\Models\EmployeeProfile;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $service)
    {
    }

    /* ---------------------------------------------------------------------
     |  EMPLOYEE (role = student)  — self service
     * -------------------------------------------------------------------*/

    /** Employee's own monthly attendance sheet + calendar. */
    public function myAttendance(Request $request): View
    {
        $user  = $request->user();
        $year  = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        return view('attendance::my', [
            'user'    => $user,
            'year'    => $year,
            'month'   => $month,
            'summary' => $this->service->monthlySummary($user->id, $year, $month),
            'map'     => $this->service->monthMap($user->id, $year, $month),
            'today'   => Attendance::where('user_id', $user->id)
                ->whereDate('attendance_date', today())->first(),
        ]);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $this->service->checkIn($request->user()->id);

        return back()->with('success', 'Checked in. Have a productive day!');
    }

    public function checkOut(Request $request): RedirectResponse
    {
        $this->service->checkOut($request->user()->id);

        return back()->with('success', 'Checked out. See you tomorrow!');
    }

    /* ---------------------------------------------------------------------
     |  HR (role = instructor)  — team management
     * -------------------------------------------------------------------*/

    /** HR view of their team with today's status + a bulk-mark form. */
    public function team(Request $request): View
    {
        $hr   = $request->user();
        $date = $request->input('date', today()->toDateString());
        $team = $this->teamMembers($hr);

        $marked = Attendance::whereIn('user_id', $team->pluck('id'))
            ->whereDate('attendance_date', $date)
            ->get()->keyBy('user_id');

        return view('attendance::team', [
            'hr'       => $hr,
            'date'     => $date,
            'team'     => $team,
            'marked'   => $marked,
            'statuses' => Attendance::STATUS_LABELS,
            'dayTypes' => Attendance::DAY_TYPE_LABELS,
        ]);
    }

    /** HR bulk/single marks attendance for selected team members. */
    public function bulkStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date'       => ['required', 'date'],
            'status'     => ['required', 'string'],
            'day_type'   => ['nullable', 'string'],
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer'],
        ]);

        if (! in_array($data['status'], Attendance::STATUSES, true)) {
            return back()->with('error', 'Invalid status.');
        }
        $dayType = ($data['day_type'] ?? null) ?: null;
        if ($dayType !== null && ! in_array($dayType, Attendance::DAY_TYPES, true)) {
            return back()->with('error', 'Invalid day type.');
        }

        $hr      = $request->user();
        $allowed = $this->teamMembers($hr)->pluck('id')->all();
        $targets = array_values(array_intersect($data['user_ids'], $allowed)); // access guard

        if (empty($targets)) {
            return back()->with('error', 'None of the selected employees are in your team.');
        }

        $n = $this->service->bulkMark($targets, $data['date'], $data['status'], $hr->id, $dayType);

        return back()->with('success', "Attendance marked for {$n} employee(s).");
    }

    /** HR monthly workspace: one employee's day-by-day attendance with team context. */
    public function teamSheet(Request $request): View
    {
        $hr    = $request->user();
        $year  = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $team  = $this->teamMembers($hr);
        $profiles = EmployeeProfile::whereIn('user_id', $team->pluck('id'))->get()->keyBy('user_id');

        $selected = $team->firstWhere('id', (int) $request->input('employee_id')) ?? $team->first();
        $first = Carbon::create($year, $month, 1)->startOfMonth();
        $selectedDate = Carbon::parse($request->input('date', now()->toDateString()));
        if (! $selectedDate->betweenIncluded($first, (clone $first)->endOfMonth())) {
            $selectedDate = $first;
        }

        $rows = $team->map(fn (User $u) => [
            'user'    => $u,
            'summary' => $this->service->monthlySummary($u->id, $year, $month),
        ]);

        return view('attendance::team-sheet', [
            'hr' => $hr,
            'year' => $year,
            'month' => $month,
            'team' => $team,
            'rows' => $rows,
            'selected' => $selected,
            'profiles' => $profiles,
            'selectedProfile' => $selected ? $profiles->get($selected->id) : null,
            'summary' => $selected ? $this->service->monthlySummary($selected->id, $year, $month) : null,
            'map' => $selected ? $this->service->monthMap($selected->id, $year, $month) : collect(),
            'selectedDate' => $selectedDate,
            'selectedRecord' => $selected ? Attendance::where('user_id', $selected->id)
                ->whereDate('attendance_date', $selectedDate)->first() : null,
        ]);
    }

    /** HR updates one employee's attendance for one selected day. */
    public function storeIndividualDay(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'status' => ['required', 'string'],
            'day_type' => ['nullable', 'string'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'quick_preset' => ['nullable', 'in:0930_1830,0940_1900'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $quickTimes = [
            '0930_1830' => ['check_in' => '09:30', 'check_out' => '18:30'],
            '0940_1900' => ['check_in' => '09:40', 'check_out' => '19:00'],
        ];

        if (! in_array($data['status'], Attendance::STATUSES, true)) {
            return back()->with('error', 'Invalid attendance status.');
        }
        $dayType = ($data['day_type'] ?? null) ?: null;
        if ($dayType !== null && ! in_array($dayType, Attendance::DAY_TYPES, true)) {
            return back()->with('error', 'Invalid day type.');
        }

        $employee = $this->teamMembers($request->user())->firstWhere('id', (int) $data['employee_id']);
        if (! $employee) {
            return back()->with('error', 'That employee is not assigned to you.');
        }

        // A quick-preset button always means "present all day, standard hours".
        $preset = $quickTimes[$data['quick_preset'] ?? ''] ?? null;
        $this->service->mark($employee->id, $data['date'], $preset ? Attendance::PP : $data['status'], [
            'day_type' => $preset ? null : $dayType,
            'check_in' => $preset['check_in'] ?? ($data['check_in'] ?? null),
            'check_out' => $preset['check_out'] ?? ($data['check_out'] ?? null),
            'remarks' => $data['remarks'] ?? null,
            'marked_by' => $request->user()->id,
            'source' => 'manual',
        ]);

        return redirect()->route('hr.attendance.sheet', [
            'year' => $data['year'], 'month' => $data['month'],
            'employee_id' => $employee->id, 'date' => $data['date'],
        ])->with('success', $preset
            ? "Quick attendance saved for {$employee->name}."
            : "Attendance updated for {$employee->name}.");
    }

    /**
     * HR: soft-delete one employee's attendance row for one day. The row is
     * kept (deleted_at flag) but excluded from the sheet, every report/export
     * and payroll's loss-of-pay calc — a finalized payroll run that included
     * the day is flagged stale so HR can recalculate it.
     */
    public function destroyDay(Request $request, Attendance $attendance): RedirectResponse
    {
        $employee = $this->teamMembers($request->user())->firstWhere('id', $attendance->user_id);
        if (! $employee) {
            return back()->with('error', 'That attendance record is not for one of your team members.');
        }

        $date = $attendance->attendance_date;
        $attendance->delete();

        return redirect()->route('hr.attendance.sheet', [
            'year'        => (int) $request->input('year', $date->year),
            'month'       => (int) $request->input('month', $date->month),
            'employee_id' => $employee->id,
            'date'        => $date->toDateString(),
        ])->with('success', "Attendance for {$employee->name} on {$date->format('d M Y')} deleted. It stays recoverable in the database.");
    }

    /**
     * Fill all unmarked days in a month with realistic random office times for
     * ONE employee. Saturdays are filled like any working day; Sundays and days
     * that already have an attendance row are left as they are.
     */
    public function fillMonthWithRandomTimes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $employee = $this->teamMembers($request->user())->firstWhere('id', (int) $data['employee_id']);
        if (! $employee) {
            return back()->with('error', 'That employee is not assigned to you.');
        }

        $result = $this->service->fillMonthForUsers(
            [$employee->id], (int) $data['year'], (int) $data['month'], $request->user()->id,
        );

        return redirect()->route('hr.attendance.sheet', [
            'year' => $data['year'], 'month' => $data['month'], 'employee_id' => $employee->id,
        ])->with('success', "Monthly attendance added for {$employee->name}: {$result['days_filled']} day(s) filled with random office times (Sundays left blank).");
    }

    /**
     * Bulk version of fillMonthWithRandomTimes(): fill the month for EVERY
     * employee on this HR's team, in one batched operation. Same rules as the
     * single-employee fill (Sundays skipped, existing days untouched).
     */
    public function fillMonthAllWithRandomTimes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $teamIds = $this->teamMembers($request->user())->pluck('id')->all();
        if (empty($teamIds)) {
            return back()->with('error', 'No employees are assigned to you yet.');
        }

        $result = $this->service->fillMonthForUsers(
            $teamIds, (int) $data['year'], (int) $data['month'], $request->user()->id,
        );

        return redirect()->route('hr.attendance.sheet', [
            'year' => $data['year'], 'month' => $data['month'],
        ])->with('success', "Monthly attendance filled for {$result['employees']} employee(s): {$result['days_filled']} day(s) added with random office times. Sundays and days that already had attendance were left unchanged.");
    }

    /** HR: export the team monthly sheet as csv/xls/pdf. */
    public function exportTeamSheet(Request $request, SheetExporter $exporter)
    {
        $format = $request->input('format', 'xlsx');
        $year   = (int) $request->input('year', now()->year);
        $month  = (int) $request->input('month', now()->month);
        $team   = $this->teamMembers($request->user());

        $headers = ['Employee', 'Present', 'Absent', 'Half', 'Leave', 'WFH', 'Holiday', 'LOP', 'Payable', 'Working'];
        $rows = $team->map(function (User $u) use ($year, $month) {
            $s = $this->service->monthlySummary($u->id, $year, $month);

            return [$u->name, $s['present'], $s['absent'], $s['half_day'], $s['leave'],
                $s['wfh'], $s['holiday'], $s['lop_days'], $s['payable_days'], $s['working_days']];
        })->all();

        $title = 'Attendance '.Carbon::create($year, $month, 1)->format('F Y');

        return $exporter->download($format, $title, $headers, $rows, 'landscape');
    }

    /** HR: download the selected employee's day-by-day monthly attendance. */
    public function exportEmployeeSheet(Request $request, SheetExporter $exporter)
    {
        $format = $request->input('format', 'xlsx');
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $employee = $this->teamMembers($request->user())->firstWhere('id', (int) $request->input('employee_id'));

        abort_unless($employee, 404);

        $map = $this->service->monthMap($employee->id, $year, $month);
        $first = Carbon::create($year, $month, 1);

        // The PDF is a biometric-style monthly register: all days across one
        // landscape page, with In / Out / status in each day cell. Other export
        // formats remain simple daily rows for spreadsheet use.
        if ($format === 'pdf') {
            $profile = EmployeeProfile::where('user_id', $employee->id)->first();
            $summary = $this->service->monthlySummary($employee->id, $year, $month);
            $days = collect(range(1, $first->daysInMonth))->map(fn (int $day) => [
                'date' => Carbon::create($year, $month, $day),
                'record' => $map->get($day),
            ]);

            // Weekly-off count for the header summary block: the configured
            // rest day of the week, on dates with no attendance row at all
            // (an employee who worked their weekly off is marked Present that
            // day and must not be double-counted as also off).
            $offDay = (int) config('payroll.attendance.weekly_off_day', Carbon::SUNDAY);
            $weeklyOffs = $days->filter(fn ($d) => $d['date']->dayOfWeek === $offDay && ! $d['record'])->count();

            $company = currentCompany();

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('attendance::employee-register-pdf', [
                'employee'   => $employee,
                'profile'    => $profile,
                'summary'    => $summary,
                'first'      => $first,
                'days'       => $days,
                'weeklyOffs' => $weeklyOffs,
                'company'    => [
                    'name'    => $company->name ?? config('app.name'),
                    'address' => $company?->addressLine() ?? '',
                ],
            ])->render(), 'UTF-8');
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$employee->name.' Attendance Register '.$first->format('F Y').'.pdf"',
            ]);
        }

        $rows = [];
        for ($day = 1; $day <= $first->daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            $record = $map->get($day);
            $rows[] = [
                $date->format('d M Y'), $date->format('D'),
                $record?->shortCode() ?? 'Not marked',
                $record ? $record->label() : '',
                $record->check_in ?? '', $record->check_out ?? '', $record->remarks ?? '',
            ];
        }

        return $exporter->download(
            $format,
            $employee->name.' Attendance '.$first->format('F Y'),
            ['Date', 'Day', 'Code', 'Status', 'Check in', 'Check out', 'Remarks'],
            $rows,
        );
    }

    /** Employee: export own monthly attendance sheet (daily rows). */
    public function exportMySheet(Request $request, SheetExporter $exporter)
    {
        $format = $request->input('format', 'xlsx');
        $year   = (int) $request->input('year', now()->year);
        $month  = (int) $request->input('month', now()->month);
        $user   = $request->user();
        $map    = $this->service->monthMap($user->id, $year, $month);

        $start = Carbon::create($year, $month, 1);
        $headers = ['Date', 'Day', 'Code', 'Status', 'Check In', 'Check Out'];
        $rows = [];
        for ($d = 1; $d <= $start->daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d);
            $rec  = $map->get($d);
            $rows[] = [$date->format('d M Y'), $date->format('D'),
                $rec?->shortCode() ?? '-', $rec ? $rec->label() : '',
                $rec->check_in ?? '', $rec->check_out ?? ''];
        }

        $title = $user->name.' Attendance '.$start->format('F Y');

        return $exporter->download($format, $title, $headers, $rows);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Employees an HR manages in the active company.
     *
     * Delegates to EmployeeProfile::teamUserIds(), which only falls back to
     * the legacy cross-company coach_id link when NO company is bound. Falling
     * back whenever the company-scoped list was empty (the old behaviour here)
     * leaked another company's employees into this HR's attendance screen the
     * moment the active company itself had none under reporting_hr_id yet.
     */
    private function teamMembers(User $hr): Collection
    {
        return User::whereIn('id', EmployeeProfile::teamUserIds($hr))->orderBy('name')->get();
    }
}
