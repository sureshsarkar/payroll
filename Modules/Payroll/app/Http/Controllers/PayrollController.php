<?php

namespace Modules\Payroll\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
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

    /** HR: submit the draft run for Super-Admin approval. */
    public function submit(Request $request, PayrollRun $run): RedirectResponse
    {
        $ok = $this->runs->submit($run, $request->user()->id);

        return back()->with($ok ? 'success' : 'error',
            $ok ? 'Payroll submitted for approval.' : 'Run cannot be submitted (empty or already submitted).');
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
