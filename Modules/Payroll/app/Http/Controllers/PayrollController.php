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
use Modules\Payroll\app\Models\PayrollItem;
use Modules\Payroll\app\Models\PayrollRun;
use Modules\Payroll\app\Services\PayrollRunService;
use Modules\Payroll\app\Support\EcrSheet;
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
            'runs'    => PayrollRun::orderByDesc('year')->orderByDesc('month')->get(),
            'deleted' => PayrollRun::onlyTrashed()->orderByDesc('deleted_at')->get(),
            'now'     => now(),
        ]);
    }

    /**
     * HR: soft-delete a whole payroll run from the list. The header row is kept
     * with a deleted_at flag — it drops off the runs list, the dashboards and
     * (via the run relation) every employee's payslip screen — but stays
     * recoverable from "Deleted runs". A PAID run cannot be deleted.
     */
    public function destroyRun(PayrollRun $run): RedirectResponse
    {
        abort_unless($run->isDeletable(), 403, 'A paid run cannot be deleted.');

        $run->delete();

        return redirect()->route('hr.payroll.index')
            ->with('success', $run->periodLabel().' run deleted. Restore it from “Deleted runs” if needed.');
    }

    /** HR: restore a soft-deleted payroll run (with its payslips) back to the list. */
    public function restoreRun(PayrollRun $run): RedirectResponse
    {
        $run->restore();

        return redirect()->route('hr.payroll.index')
            ->with('success', $run->periodLabel().' run restored.');
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
            return back()->with('error', 'This run is already '.strtolower($run->statusLabel()).' and can no longer be edited. Reopen it for correction first.');
        }

        $res = $this->runs->prepare($run, $team);
        $msg = "Prepared {$res['prepared']} payslip(s) for {$run->periodLabel()}.";
        if (! empty($res['skipped'])) {
            $msg .= ' Skipped '.count($res['skipped']).' employee(s) without a salary structure.';
        }

        return redirect()->route('hr.payroll.show', $run)->with('success', $msg);
    }

    /**
     * HR: soft-delete one employee's payslip from a run. The row is kept
     * (deleted_at flag) but excluded from the run totals, every export and the
     * employee's own payslip list. Not allowed once the run is PAID; on a
     * finalized run the stored payslip PDF stays on disk but the item no longer
     * resolves, so the employee can't download it.
     */
    public function destroyItem(Request $request, PayrollRun $run, PayrollItem $item): RedirectResponse
    {
        abort_unless($item->payroll_run_id === $run->id, 404);
        abort_if($run->status === PayrollRun::PAID, 403, 'A paid run cannot be changed.');
        $this->guardTeamAccess($request, $item->employee ?: User::findOrFail($item->user_id));

        $item->delete();

        $run->update([
            'employee_count' => $run->items()->count(),
            'total_net'      => (float) $run->items()->sum('net_pay'),
        ]);

        return redirect()->route('hr.payroll.show', $run)
            ->with('success', 'Payslip deleted. It is excluded from totals and exports but kept in the database.');
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
     * Export the run as a monthly EPF ECR salary sheet — one row per employee
     * in the PF-portal layout (UAN / NAME / gross / EPF-EPS-EDLI wages /
     * employee & employer shares / NCP / DED). Excel (?format=xls|xlsx) or CSV
     * (?format=csv). Every employee in the run is included in a single sheet.
     */
    public function exportEcr(Request $request, PayrollRun $run)
    {
        $format = strtolower((string) $request->input('format', 'xlsx'));
        abort_unless(in_array($format, ['xls', 'xlsx', 'csv'], true), 400);

        $items = $run->items()->with('employee')->get();
        abort_if($items->isEmpty(), 404);

        $profiles = EmployeeProfile::whereIn('user_id', $items->pluck('user_id'))->get()->keyBy('user_id');
        $sheet    = new EcrSheet($run, $items, $profiles);

        [$body, $ext, $contentType] = $format === 'csv'
            ? [$sheet->csv(), 'csv', 'text/csv; charset=UTF-8']
            : [$sheet->xls(), 'xls', 'application/vnd.ms-excel; charset=UTF-8'];

        return response($body, 200, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$sheet->filename($ext).'"',
        ]);
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

        $filename = 'Salary Sheet - '.($employee->name ?: 'Employee '.$employee->id).' - '.$run->periodLabel().'.pdf';

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
        $items = $this->slipItemsFor($request, $run);
        abort_if($items->isEmpty(), 404);

        $filename = 'Payslips - '.$run->periodLabel().'.pdf';

        return response($payslip->renderMany($items), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Every employee's Form IV salary slip in the run as one PDF, laid out as a
     * multi-employee "Register of Payment of Wages / Salary" — as many rows per
     * page as fit, the company header block repeating at the top of every page
     * and the serial numbering running unbroken across pages. Team scoping
     * matches exportPayslips().
     */
    public function exportSalarySlips(Request $request, PayrollRun $run)
    {
        $items = $this->slipItemsFor($request, $run);
        abort_if($items->isEmpty(), 404);

        $filename = 'Salary Sheets - '.$run->periodLabel().'.pdf';

        return response($this->registerPdf($run, $items), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * The run's items this requester may export slips for: their own team for a
     * regular HR, every item for Super Admin.
     *
     * @return \Illuminate\Support\Collection<int, \Modules\Payroll\app\Models\PayrollItem>
     */
    private function slipItemsFor(Request $request, PayrollRun $run): Collection
    {
        $items = $run->items()->with('employee')->get();

        if (! $request->user('admin')) {
            $teamIds = $this->teamMembers($request->user())->pluck('id');
            $items = $items->whereIn('user_id', $teamIds)->values();
        }

        return $items;
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
     * export, the "all salary slips" batch and the single-employee slip so all
     * three stay pixel-identical — the register simply grows or shrinks with
     * the number of rows, paginating with a repeated header when it overflows.
     *
     * @param  \Illuminate\Support\Collection<int, \Modules\Payroll\app\Models\PayrollItem>  $items
     */
    private function registerPdf(PayrollRun $run, Collection $items): string
    {
        [$profiles, $summaries] = $this->registerContext($items, $run);
        $register = (new WageRegisterFormatter())->build($items, $summaries, $profiles);

        return $this->renderRegisterPdf('payroll::wage-register-pdf', [
            'run'           => $run,
            'rows'          => $register['rows'],
            'totals'        => $register['totals'],
            'establishment' => Establishment::forRun($run),
        ]);
    }

    /**
     * Employee profiles + attendance summaries keyed by user_id for the given
     * items and run month — Form IV needs the day columns (worked / weekly-off
     * / holiday / leave / pay days) and the identity block.
     *
     * @param  \Illuminate\Support\Collection<int, \Modules\Payroll\app\Models\PayrollItem>  $items
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function registerContext(Collection $items, PayrollRun $run): array
    {
        $profiles = EmployeeProfile::whereIn('user_id', $items->pluck('user_id'))
            ->with('department')->get()->keyBy('user_id');

        $attendance = app(AttendanceService::class);
        $summaries = $items->mapWithKeys(fn ($item) => [
            $item->user_id => $attendance->monthlySummary($item->user_id, $run->year, $run->month),
        ]);

        return [$profiles, $summaries];
    }

    /** Render a landscape A4 register view to raw PDF bytes. */
    private function renderRegisterPdf(string $view, array $data): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        // "Page X of Y" in the top strip of every page. The company header
        // block itself repeats via a position:fixed element in the view; the
        // page number is the one piece that can only be known post-layout.
        $pdf->getCanvas()->page_script(static function ($pageNumber, $pageCount, $canvas, $fontMetrics): void {
            $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $text = 'Page '.$pageNumber.' of '.$pageCount;
            $size = 9;
            $x = $canvas->get_width() - $fontMetrics->getTextWidth($text, $font, $size) - 12;
            $canvas->text($x, 12, $text, $font, $size, [0, 0, 0]);
        });

        return $pdf->output();
    }

    /**
     * HR: finalise the draft run directly — no Super Admin approval step. The
     * run is locked, payslip PDFs are generated and employees can download
     * their payslips straight away.
     */
    public function submit(Request $request, PayrollRun $run): RedirectResponse
    {
        $ok = $this->runs->finalize($run, $request->user()->id);

        return back()->with($ok ? 'success' : 'error',
            $ok ? 'Payroll finalized. Payslips generated and available to employees.'
                : 'Run cannot be finalized (empty or already finalized).');
    }

    /**
     * HR: pull a finalized (or submitted) run back to draft — e.g. attendance
     * was corrected afterwards and the numbers need to be re-prepared. Never
     * for a PAID run.
     */
    public function reopen(PayrollRun $run): RedirectResponse
    {
        $ok = $this->runs->reopen($run);

        return redirect()->route('hr.payroll.show', $run)->with($ok ? 'success' : 'error',
            $ok ? 'Run reopened for correction — prepare it again to pick up the updated attendance, then finalize.'
                : 'Only a submitted or finalized (not yet paid) run can be reopened.');
    }

    /**
     * Recompute every payslip in a finalized run from current attendance/leave
     * data and regenerate the PDFs — for when an absence or leave was corrected
     * after finalizing. Excluded once the run is PAID. Reachable by both HR
     * (hr.payroll.recalculate) and Super Admin (admin.payroll.recalculate).
     */
    public function recalculate(PayrollRun $run): RedirectResponse
    {
        $count = $this->runs->recalculate($run);

        return back()->with($count > 0 ? 'success' : 'error',
            $count > 0
                ? "Recalculated {$count} payslip(s) from current attendance/leave data."
                : 'Only a finalized (not yet paid) run can be recalculated.');
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

    /**
     * Super Admin: approve a submitted run and generate payslip PDFs. Retained
     * as a fallback — HR now finalizes runs directly (see submit()), so runs
     * normally never sit in the hr_submitted state this acts on.
     */
    public function approve(Request $request, PayrollRun $run): RedirectResponse
    {
        $adminId = $request->user('admin')?->id;
        $ok = $this->runs->approve($run, $adminId);

        return back()->with($ok ? 'success' : 'error',
            $ok ? 'Payroll approved. Payslips generated.' : 'Only submitted runs can be approved.');
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
