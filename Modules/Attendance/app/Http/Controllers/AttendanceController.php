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
            'statuses' => Attendance::STATUSES,
        ]);
    }

    /** HR bulk/single marks attendance for selected team members. */
    public function bulkStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date'       => ['required', 'date'],
            'status'     => ['required', 'string'],
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer'],
        ]);

        if (! in_array($data['status'], Attendance::STATUSES, true)) {
            return back()->with('error', 'Invalid status.');
        }

        $hr      = $request->user();
        $allowed = $this->teamMembers($hr)->pluck('id')->all();
        $targets = array_values(array_intersect($data['user_ids'], $allowed)); // access guard

        if (empty($targets)) {
            return back()->with('error', 'None of the selected employees are in your team.');
        }

        $n = $this->service->bulkMark($targets, $data['date'], $data['status'], $hr->id);

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

        $employee = $this->teamMembers($request->user())->firstWhere('id', (int) $data['employee_id']);
        if (! $employee) {
            return back()->with('error', 'That employee is not assigned to you.');
        }

        $preset = $quickTimes[$data['quick_preset'] ?? ''] ?? null;
        $this->service->mark($employee->id, $data['date'], $preset ? Attendance::PRESENT : $data['status'], [
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

    /** Fill all unmarked weekdays in a month with realistic random office times. */
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

        $first = Carbon::create($data['year'], $data['month'], 1)->startOfMonth();
        $existingDates = Attendance::where('user_id', $employee->id)
            ->whereBetween('attendance_date', [$first->toDateString(), (clone $first)->endOfMonth()->toDateString()])
            ->pluck('attendance_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();

        $created = 0;
        for ($day = 1; $day <= $first->daysInMonth; $day++) {
            $date = Carbon::create($data['year'], $data['month'], $day);
            if ($date->isWeekend() || $existingDates->has($date->toDateString())) {
                continue;
            }

            $checkInMinutes = (9 * 60) + random_int(30, 40);
            $checkOutMinutes = (18 * 60) + random_int(30, 60);
            $this->service->mark($employee->id, $date, Attendance::PRESENT, [
                'check_in' => sprintf('%02d:%02d', intdiv($checkInMinutes, 60), $checkInMinutes % 60),
                'check_out' => sprintf('%02d:%02d', intdiv($checkOutMinutes, 60), $checkOutMinutes % 60),
                'marked_by' => $request->user()->id,
                'source' => 'manual',
                'remarks' => 'Monthly attendance quick fill',
            ]);
            $created++;
        }

        return redirect()->route('hr.attendance.sheet', [
            'year' => $data['year'], 'month' => $data['month'], 'employee_id' => $employee->id,
        ])->with('success', "Monthly attendance added for {$employee->name}: {$created} weekday(s) filled with random office times.");
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

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('attendance::employee-register-pdf', compact(
                'employee', 'profile', 'summary', 'first', 'days'
            ))->render(), 'UTF-8');
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
                $date->format('d M Y'), $date->format('D'), $record->status ?? 'Not marked',
                $record->check_in ?? '', $record->check_out ?? '', $record->remarks ?? '',
            ];
        }

        return $exporter->download(
            $format,
            $employee->name.' Attendance '.$first->format('F Y'),
            ['Date', 'Day', 'Status', 'Check in', 'Check out', 'Remarks'],
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
        $headers = ['Date', 'Day', 'Status', 'Check In', 'Check Out'];
        $rows = [];
        for ($d = 1; $d <= $start->daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d);
            $rec  = $map->get($d);
            $rows[] = [$date->format('d M Y'), $date->format('D'),
                $rec->status ?? '-', $rec->check_in ?? '', $rec->check_out ?? ''];
        }

        $title = $user->name.' Attendance '.$start->format('F Y');

        return $exporter->download($format, $title, $headers, $rows);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Employees an HR manages. Primary link is employee_profiles.reporting_hr_id;
     * during the LMS->payroll transition we fall back to the legacy coach_id link
     * so the screen isn't empty before profiles are assigned.
     */
    private function teamMembers(User $hr): Collection
    {
        $ids = EmployeeProfile::where('reporting_hr_id', $hr->id)->pluck('user_id');

        if ($ids->isEmpty()) {
            $ids = User::where('role', 'student')->where('coach_id', $hr->id)->pluck('id');
        }

        return User::whereIn('id', $ids)->orderBy('name')->get();
    }
}
