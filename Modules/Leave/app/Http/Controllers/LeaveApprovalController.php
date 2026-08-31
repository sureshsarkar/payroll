<?php

namespace Modules\Leave\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\HrEmployee\app\Models\EmployeeProfile;
use Modules\Leave\app\Models\Leave;
use Modules\Leave\app\Services\LeaveService;

/**
 * HR-side leave approvals (role=instructor). HR only sees/acts on their team.
 */
class LeaveApprovalController extends Controller
{
    public function __construct(private readonly LeaveService $service)
    {
    }

    public function index(Request $request): View
    {
        $teamIds = $this->teamMembers($request->user())->pluck('id');

        return view('leave::approvals', [
            'pending' => Leave::with(['type', 'employee'])
                ->whereIn('user_id', $teamIds)
                ->where('status', Leave::PENDING)
                ->orderBy('start_date')->get(),
            'recent'  => Leave::with(['type', 'employee'])
                ->whereIn('user_id', $teamIds)
                ->whereIn('status', [Leave::APPROVED, Leave::REJECTED])
                ->orderByDesc('reviewed_at')->limit(20)->get(),
        ]);
    }

    public function approve(Request $request, Leave $leave): RedirectResponse
    {
        $this->authorizeTeam($request, $leave);
        $ok = $this->service->approve($leave, $request->user()->id);

        return back()->with($ok ? 'success' : 'error',
            $ok ? 'Leave approved — attendance updated.' : 'This request is no longer pending.');
    }

    public function reject(Request $request, Leave $leave): RedirectResponse
    {
        $this->authorizeTeam($request, $leave);
        $note = $request->input('note');
        $ok = $this->service->reject($leave, $request->user()->id, $note);

        return back()->with($ok ? 'success' : 'error',
            $ok ? 'Leave rejected.' : 'This request is no longer pending.');
    }

    private function authorizeTeam(Request $request, Leave $leave): void
    {
        abort_unless(
            $this->teamMembers($request->user())->pluck('id')->contains($leave->user_id),
            403,
        );
    }

    /**
     * Employees an HR manages in the active company. Delegates to
     * EmployeeProfile::teamUserIds() — see AttendanceController::teamMembers()
     * for why falling back to coach_id whenever the scoped list was empty
     * leaked cross-company employees into leave approvals.
     */
    private function teamMembers(User $hr): Collection
    {
        return User::whereIn('id', EmployeeProfile::teamUserIds($hr))->orderBy('name')->get();
    }
}
