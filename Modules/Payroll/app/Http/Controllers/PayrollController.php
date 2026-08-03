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
use Modules\HrEmployee\app\Models\EmployeeProfile;
use Modules\Payroll\app\Models\PayrollRun;
use Modules\Payroll\app\Services\PayrollRunService;

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
        return view('payroll::run-show', [
            'run'   => $run,
            'items' => $run->items()->with('employee')->get(),
        ]);
    }

    /** Export a payroll run (company-wide payroll sheet) as csv/xls/pdf. */
    public function exportRun(Request $request, PayrollRun $run, SheetExporter $exporter)
    {
        $format = $request->input('format', 'xlsx');
        $items = $run->items()->with('employee')->get();

        if ($format === 'pdf') {
            $profiles = EmployeeProfile::whereIn('user_id', $items->pluck('user_id'))
                ->with('department')->get()->keyBy('user_id');
            $earningHeads = $items->flatMap(fn ($item) => collect($item->earnings ?? [])->pluck('name'))
                ->filter()->unique()->values();
            $deductionHeads = $items->flatMap(fn ($item) => collect($item->deductions ?? [])->pluck('name'))
                ->filter()->unique()->values();

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('payroll::wage-register-pdf', compact(
                'run', 'items', 'profiles', 'earningHeads', 'deductionHeads'
            ))->render(), 'UTF-8');
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();

            return response($pdf->output(), 200, [
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

    /** HR: submit the draft run for Super-Admin approval. */
    public function submit(Request $request, PayrollRun $run): RedirectResponse
    {
        $ok = $this->runs->submit($run, $request->user()->id);

        return back()->with($ok ? 'success' : 'error',
            $ok ? 'Payroll submitted for approval.' : 'Run cannot be submitted (empty or already submitted).');
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
        return view('payroll::admin-run-show', [
            'run'   => $run,
            'items' => $run->items()->with('employee')->get(),
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

    private function teamMembers(User $hr): Collection
    {
        $ids = EmployeeProfile::where('reporting_hr_id', $hr->id)->pluck('user_id');
        if ($ids->isEmpty()) {
            $ids = User::where('role', 'student')->where('coach_id', $hr->id)->pluck('id');
        }

        return User::whereIn('id', $ids)->orderBy('name')->get();
    }
}
