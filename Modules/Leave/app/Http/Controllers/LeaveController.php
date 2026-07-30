<?php

namespace Modules\Leave\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Leave\app\Models\Leave;
use Modules\Leave\app\Models\LeaveBalance;
use Modules\Leave\app\Models\LeaveType;
use Modules\Leave\app\Services\LeaveService;

/**
 * Employee self-service leave (role=student): view balances, apply, cancel.
 */
class LeaveController extends Controller
{
    public function __construct(private readonly LeaveService $service)
    {
    }

    public function index(Request $request): View
    {
        $user  = $request->user();
        $types = LeaveType::active()->orderBy('name')->get();

        // ensure a balance row exists for each paid type this year
        foreach ($types->where('is_paid', true) as $type) {
            $this->service->balanceFor($user->id, $type);
        }

        return view('leave::my', [
            'types'    => $types,
            'balances' => LeaveBalance::with('type')
                ->where('user_id', $user->id)
                ->where('year', now()->year)->get(),
            'leaves'   => Leave::with('type')
                ->where('user_id', $user->id)
                ->orderByDesc('start_date')->limit(50)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['required', 'date'],
            'reason'        => ['nullable', 'string', 'max:500'],
            'half_day'      => ['nullable', 'boolean'],
        ]);

        $type = LeaveType::active()->findOrFail($data['leave_type_id']);

        try {
            $this->service->apply(
                $request->user()->id, $type,
                $data['start_date'], $data['end_date'],
                $data['reason'] ?? null, (bool) ($data['half_day'] ?? false),
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', 'Leave request submitted.');
    }

    public function cancel(Request $request, Leave $leave): RedirectResponse
    {
        abort_unless($leave->user_id === $request->user()->id, 403);

        $ok = $this->service->cancel($leave);

        return back()->with($ok ? 'success' : 'error',
            $ok ? 'Leave cancelled.' : 'Only pending requests can be cancelled.');
    }
}
