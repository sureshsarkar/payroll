<?php

namespace Modules\Payroll\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SheetExporter;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\Attendance\app\Services\AttendanceService;
use Modules\HrEmployee\app\Models\EmployeeProfile;
use Modules\Payroll\app\Models\PayrollRun;
use Modules\Payroll\app\Services\PayrollRunService;
use Modules\Payroll\app\Support\Establishment;
use Modules\Payroll\app\Support\FormXiPayslip;
use Modules\Payroll\app\Support\WageRegisterFormatter;

/**
 * HR-side payroll workflow (prepare -> submit) plus the Super-Admin approval
 * action. Access is split by middleware in routes/web.php:
 *   instructorrole  -> runs / prepare / show / submit
 *   auth:admin      -> approve
 */
class PayrollController extends Controller
{
    public function __construct(private readonly PayrollRunService $runs)
    {
    }

    /** HR: list payroll runs + "prepare this month" control. */
    public function index(): View
    {
        return view('payroll::runs', [
            'runs' => PayrollRun::orderByDesc('year')->orderByDesc('month')->get(),
            'now'  => now(),
        ]);
    }

    /** HR: create/refresh a draft run and compute items for the team. */
    public function prepare(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year'  => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $hr   = $request->user();
        $team = $this->teamMembers($hr)->pluck('id')->all();

        if (empty($team)) {
            return back()->with('error', 'No employees assigned to you yet.');
        }

        $run = $this->runs->createOrGetRun($data['year'], $data['month'], $hr->id);

        if (! $run->isEditable()) {
            return back()->with('error', 'This run is already '.$run->status.' and can no longer be edited.');
        }

        $res = $this->runs->prepare($run, $team);
        $msg = "Prepared {$res['prepared']} payslip(s) for {$run->periodLabel()}.";
        if (! empty($res['skipped'])) {
            $msg .= ' Skipped '.count($res['skipped']).' employee(s) without a salary structure.';
        }

        return redirect()->route('hr.payroll.show', $run)->with('success', $msg);
    }

    /** HR: view a run's computed items. */
    public function show(PayrollRun $run): View
    {
        $items = $run->items()->with('employee')->get();

        return view('payroll::run-show', [
            'run'       => $run,
            'items'     => $items,
            'staleIds'  => $items->filter(fn ($item) => $item->isStale($run))->pluck('id'),
        ]);
    }

    /** Export a payroll run (company-wide payroll sheet) as csv/xls/pdf. */
    public function exportRun(Request $request, PayrollRun $run, SheetExporter $exporter)
    {
        $format = $request->input('format', 'xlsx');
        $items = $run->items()->with('employee')->get();

        if ($format === 'pdf') {
            return response($this->registerPdf($run, $items), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="Salary Register '.$run->periodLabel().'.pdf"',
            ]);
        }

        $headers = ['Employee', 'Emp ID', 'Payable Days', 'LOP Days', 'Gross', 'Deductions', 'Net Pay'];
        $rows = $items->map(fn ($it) => [
            $it->employee->name ?? 'Employee #'.$it->user_id,
            $it->user_id, $it->payable_days, $it->lop_days,
            number_format((float) $it->total_earnings, 2, '.', ''),
            number_format((float) $it->total_deductions, 2, '.', ''),
            number_format((float) $it->net_pay, 2, '.', ''),
        ])->all();

        return $exporter->download($format, 'Payroll '.$run->periodLabel(), $headers, $rows, 'landscape');
    }

    /**
     * Export a single employee's Form IV wage/salary slip (one row of the
     * register, exactly as the statutory register renders it) for the given
     * run's month.
     */
    public function exportEmployee(Request $request, PayrollRun $run, User $employee)
    {
        $this->guardTeamAccess($request, $employee);

        $items = $run->items()->with('employee')->where('user_id', $employee->id)->get();
        abort_if($items->isEmpty(), 404);

        $filename = 'Salary Slip - '.($employee->name ?: 'Employee '.$employee->id).' - '.$run->periodLabel().'.pdf';

        return response($this->registerPdf($run, $items), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Export a single employee's statutory pay slip (Form XI, Rule 26(2)) for
     * the given run's month — the individual bilingual-ready wage slip.
     */
    public function exportPayslip(Request $request, PayrollRun $run, User $employee, FormXiPayslip $payslip)
    {
        $this->guardTeamAccess($request, $employee);

        $item = $run->items()->with('employee')->where('user_id', $employee->id)->firstOrFail();

        return response($payslip->render($item), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$payslip->filename($item).'"',
        ]);
    }

    /**
     * Every Form XI slip in the run as one PDF, an employee per page. An HR
     * only gets their own team's slips even though the run may hold other
     * HRs' employees too (a company can have several HR staff); Super Admin
     * gets every slip in the run.
     */
    public function exportPayslips(Request $request, PayrollRun $run, FormXiPayslip $payslip)
    {
        $items = $run->items()->with('employee')->get();
        if (! $request->user('admin')) {
            $teamIds = $this->teamMembers($request->user())->pluck('id');
            $items = $items->whereIn('user_id', $teamIds)->values();
        }
        abort_if($items->isEmpty(), 404);

        $filename = 'Payslips - '.$run->periodLabel().'.pdf';

        return response($payslip->renderMany($items), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * A regular HR (web/instructor guard) may only export slips for their own
     * team; Super Admin (admin guard) can export any employee in a run they
     * can already see. Without this, one HR could export another HR's team
     * member's payslip in the same company just by guessing the user id.
     */
    private function guardTeamAccess(Request $request, User $employee): void
    {
        if ($request->user('admin')) {
            return;
        }

        abort_unless($this->teamMembers($request->user())->pluck('id')->contains($employee->id), 403);
    }

    /**
     * Render the given payroll items as a Form IV "Register of Payment of
     * Wages / Salary" PDF and return the raw bytes. Shared by the whole-run
     * export and the single-employee slip so both stay pixel-identical.
     *
     * @param  \Illuminate\Support\Collection<int, \Modules\Payroll\app\Models\PayrollItem>  $items
     */
    private function registerPdf(PayrollRun $run, Collection $items): string
    {
        $profiles = EmployeeProfile::whereIn('user_id', $items->pluck('user_id'))
            ->with('department')->get()->keyBy('user_id');

        // Attendance summary per employee for the run's month (Form IV needs
        // the day columns: worked / weekly-off / holiday / leave / pay days).
        $attendance = app(AttendanceService::class);
        $summaries = $items->mapWithKeys(fn ($item) => [
            $item->user_id => $attendance->monthlySummary($item->user_id, $run->year, $run->month),
        ]);

        $register = (new WageRegisterFormatter())->build($items, $summaries, $profiles);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('payroll::wage-register-pdf', [
            'run'           => $run,
            'rows'          => $register['rows'],
            'totals'        => $register['totals'],
            'establishment' => Establishment::forRun($run),
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }

    /** HR: submit the draft run for Super-Admin approval. */
    public function submit(Request $request, PayrollRun $run): RedirectResponse
    {
        $ok = $this->runs->submit($run, $request->user()->id);

        return back()->with($ok ? 'success' : 'error',
            $ok ? 'Payroll submitted for approval.' : 'Run cannot be submitted (empty or already submitted).');
    }

    /**
     * HR: pull a submitted run back to draft — e.g. attendance was corrected
     * after submitting and the numbers need to be re-prepared before approval.
     */
    public function reopen(PayrollRun $run): RedirectResponse
    {
        $ok = $this->runs->reopen($run);

        return redirect()->route('hr.payroll.show', $run)->with($ok ? 'success' : 'error',
            $ok ? 'Run reopened for correction — prepare it again to pick up the updated attendance.'
                : 'Only a submitted (not yet approved) run can be reopened.');
    }

    /** Super Admin: company-wide list of payroll runs to review/approve. */
    public function adminIndex(): View
    {
        return view('payroll::admin-runs', [
            'runs' => PayrollRun::orderByDesc('year')->orderByDesc('month')->get(),
        ]);
    }

    /** Super Admin: review a run's items before approving. */
    public function adminShow(PayrollRun $run): View
    {
        $items = $run->items()->with('employee')->get();

        return view('payroll::admin-run-show', [
            'run'      => $run,
            'items'    => $items,
            'staleIds' => $items->filter(fn ($item) => $item->isStale($run))->pluck('id'),
        ]);
    }

    /** Super Admin: approve a submitted run and generate payslip PDFs. */
    public function approve(Request $request, PayrollRun $run): RedirectResponse
    {
        $adminId = $request->user('admin')?->id;
        $ok = $this->runs->approve($run, $adminId);

        return back()->with($ok ? 'success' : 'error',
            $ok ? 'Payroll approved. Payslips generated.' : 'Only submitted runs can be approved.');
    }

    /**
     * Super Admin: recompute an already-approved run's items from current
     * attendance/leave data and regenerate its payslip PDFs — for when an
     * absence or leave was corrected after approval, so the payslip stops
     * showing pre-correction numbers.
     */
    public function recalculate(PayrollRun $run): RedirectResponse
    {
        $count = $this->runs->recalculate($run);

        return back()->with($count > 0 ? 'success' : 'error',
            $count > 0
                ? "Recalculated {$count} payslip(s) from current attendance/leave data."
                : 'Only an approved (not yet paid) run can be recalculated.');
    }

    /**
     * Employees an HR manages in the active company. Delegates to
     * EmployeeProfile::teamUserIds() — see AttendanceController::teamMembers()
     * for why falling back to coach_id whenever the scoped list was empty
     * leaked cross-company employees into payroll runs.
     */
    private function teamMembers(User $hr): Collection
    {
        return User::whereIn('id', EmployeeProfile::teamUserIds($hr))->orderBy('name')->get();
    }
}
