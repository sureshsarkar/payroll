<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CourseBatch;
use App\Models\FeeDemand;
use App\Models\FeePayment;
use App\Models\TeacherBatchAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;

/**
 * Fee Management — coach-side foundation (Phase 4B).
 *
 * Coach dashboard for raising fee demands against batches and
 * tracking incoming payments. Razorpay collection + student-side
 * checkout will follow in a separate phase.
 *
 * Permission gate: 'fees' (or whatever maps in checkPermission).
 * Defence-in-depth: every query filters by the authenticated coach's
 * id so a coach can never read another coach's demands.
 */
class FeeManagementController extends Controller
{
    /**
     * 2026-07-07 (QA audit) — server-side RBAC gate. The menu/buttons were
     * hidden for staff without the permission, but the routes carried ONLY
     * `requires.membership`, so a staff member could still POST create / record
     * / refund directly. Gate every fee action by permission. A real coach
     * always passes (see App\Http\Middleware\CoachPermission); staff need the
     * matching granted slug, else in-panel 403 (web) / JSON 403 (AJAX).
     */
    public function __construct()
    {
        $this->middleware('permission:fees');
        $this->middleware('permission:fees-create')->only('storeDemand');
        $this->middleware('permission:fees-edit')->only('recordPayment');
        $this->middleware('permission:fees-delete')->only('refundPayment');
    }

    /**
     * Resolve the active coach id, honouring coach-staff impersonation
     * (staff inherit their parent coach's data scope).
     */
    private function coachId(): int
    {
        $u = userAuth();
        return (int) ($u->role === 'instructor' ? $u->id : ($u->coach_id ?? $u->id));
    }

    /**
     * 2026-05-20 P4 — Teacher panel.
     *
     * The fee module's queries are coach-scoped, which means a teacher
     * (CoachStaff) inherits their coach's full demand list out of the
     * box — they could record payments against any batch their coach
     * runs. That's the gap.
     *
     * Returns:
     *   null   — caller is the coach themselves, no batch filter
     *   []     — teacher with no active grants, must block all queries
     *   int[]  — teacher's allowed batch ids
     */
    private function teacherBatchScope(): ?array
    {
        return TeacherBatchAssignment::assignedBatchIdsFor((int) userAuth()->id);
    }

    /**
     * Abort 403 if the caller is a teacher and the supplied batch_id
     * isn't one of their granted batches. Coach calls pass through.
     */
    private function enforceTeacherCanAccessBatch(int $batchId): void
    {
        $allowed = $this->teacherBatchScope();
        if ($allowed === null) return; // coach — unbounded
        abort_unless(in_array($batchId, $allowed, true), 403,
            'This batch is not in your assigned scope.');
    }

    /**
     * GET /instructor/fees — main dashboard.
     *
     * Shows headline KPIs (total collected this month, outstanding,
     * failed, refunded) + a list of recent demands with collection %.
     */
    public function index(Request $request)
    {
        $coachId = $this->coachId();
        $teacherBatches = $this->teacherBatchScope();

        // Coach's batches — needed for the Create Demand modal.
        // For a teacher, narrow to ONLY their assigned active batches
        // (so the dropdown can't be used to raise a demand on a batch
        // they don't manage).
        $batches = CourseBatch::query()
            ->whereHas('course', function ($q) use ($coachId) {
                $q->where('instructor_id', $coachId)
                  ->orWhere('added_by', $coachId);
            })
            ->where('status', 'active')
            ->when($teacherBatches !== null, fn ($q) => $q->whereIn('id', $teacherBatches ?: [0]))
            ->with('course:id,title')
            ->orderBy('title')
            ->get(['id', 'course_id', 'title']);

        // Headline metrics scoped to the coach (KPI strip for now
        // remains coach-wide — teacher-tailored fee KPIs already live
        // on the dashboard from P1).
        $kpi = $this->kpiSnapshot($coachId);

        // Recent demands (10 most recent, with collection counts).
        // For a teacher, narrow by demand.batch_id IN allowed batches.
        $demands = FeeDemand::query()
            ->forCoach($coachId)
            ->when($teacherBatches !== null, fn ($q) => $q->whereIn('batch_id', $teacherBatches ?: [0]))
            ->with(['batch:id,course_id,title', 'batch.course:id,title'])
            ->withCount([
                'payments as paid_count' => fn ($q) => $q->where('status', 'paid'),
                'payments as failed_count' => fn ($q) => $q->where('status', 'failed'),
            ])
            ->withSum(['payments as collected' => fn ($q) => $q->where('status', 'paid')], 'amount')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        // Razorpay configuration readiness — surfaces on the dashboard
        // so a coach can see whether students will be able to actually
        // pay via gateway (vs. coach having to record manual payments
        // until the platform admin finishes setup).
        $razorpayStatus = $this->razorpayReadiness();

        return view('frontend.instructor-dashboard.fees.index', compact(
            'batches', 'demands', 'kpi', 'razorpayStatus'
        ));
    }

    /**
     * Audit 2026-05-19 Phase 5b — Razorpay readiness snapshot.
     *
     * Same probe as `php artisan razorpay:test` minus the API ping.
     * Surfaces as a status banner on /instructor/fees.
     *
     * Returns:
     *   ['state' => 'ready' | 'partial' | 'missing', 'issues' => [...]]
     */
    private function razorpayReadiness(): array
    {
        $issues = [];

        try {
            $kv = \DB::table('payment_gateways')
                ->whereIn('key', ['razorpay_key', 'razorpay_secret', 'razorpay_status'])
                ->pluck('value', 'key')
                ->all();
            $decrypted = \App\Support\SecretSettings::decryptForTable('payment_gateways', $kv);

            $key    = $decrypted['razorpay_key']    ?? null;
            $secret = $decrypted['razorpay_secret'] ?? null;
            $status = $decrypted['razorpay_status'] ?? null;

            if (!$key || $key === 'razorpay_key') {
                $issues[] = __('razorpay_key missing or placeholder');
            }
            if (!$secret || $secret === 'razorpay_secret') {
                $issues[] = __('razorpay_secret missing or placeholder');
            }
            if ($status !== 'active') {
                $issues[] = __('Razorpay status is not active');
            }
            if (empty(config('services.razorpay.webhook_secret') ?: env('RAZORPAY_WEBHOOK_SECRET'))) {
                $issues[] = __('RAZORPAY_WEBHOOK_SECRET env var missing');
            }
        } catch (\Throwable $e) {
            $issues[] = __('Could not read payment_gateways: :err', ['err' => $e->getMessage()]);
        }

        if (empty($issues)) {
            return ['state' => 'ready', 'issues' => []];
        }
        // 'partial' = at least one piece is configured but not all
        // 'missing' = razorpay_key entirely absent / placeholder
        $state = (count($issues) >= 3) ? 'missing' : 'partial';
        return ['state' => $state, 'issues' => $issues];
    }

    /**
     * POST /instructor/fees/demands — create a new demand.
     */
    public function storeDemand(Request $request)
    {
        $coachId = $this->coachId();

        $rules = [
            'title'             => 'required|string|max:255',
            'batch_id'          => 'required|exists:course_batches,id',
            'amount'            => 'required|numeric|min:0|max:9999999',
            'due_date'          => 'nullable|date',
            'late_fine_per_day' => 'nullable|numeric|min:0|max:99999',
            'notes'             => 'nullable|string|max:2000',
        ];

        $request->validate($rules, [
            'title.required'    => __('Fee title is required'),
            'batch_id.required' => __('Pick a batch'),
            'amount.required'   => __('Amount is required'),
        ]);

        // Verify the coach owns the batch.
        $batch = CourseBatch::with('course:id,instructor_id,added_by')
            ->where('id', $request->batch_id)
            ->first();

        if (!$batch || (
            (int) ($batch->course?->instructor_id ?? 0) !== $coachId &&
            (int) ($batch->course?->added_by ?? 0) !== $coachId
        )) {
            abort(403, 'This batch does not belong to you.');
        }

        // 2026-05-20 P4 — additionally enforce the teacher gate.
        // Coach passes through (teacherBatchScope returns null);
        // teacher must have an active grant on this exact batch.
        $this->enforceTeacherCanAccessBatch((int) $batch->id);

        $demand = FeeDemand::create([
            'coach_id'          => $coachId,
            'batch_id'          => $batch->id,
            'title'             => $request->title,
            'amount'            => $request->amount,
            'due_date'          => $request->due_date,
            'late_fine_per_day' => $request->late_fine_per_day ?? 0,
            'notes'             => $request->notes,
            'status'            => 'published',
            'created_by'        => userAuth()->id,
        ]);

        // Audit 2026-05-19 phase 4C — notify enrolled students.
        // Best-effort: never fail demand creation because of mail.
        try {
            // 2026-06-02 (fee-visibility fix) — notify ALL batch members, not
            // only has_access=1 ones. Unpaid members (has_access=0) are exactly
            // who the fee demand targets, so they must be notified too (matches
            // the student My Fees query).
            $studentIds = Enrollment::where('batch_id', $batch->id)
                ->pluck('user_id')
                ->unique();
            if ($studentIds->isNotEmpty()) {
                $students = \App\Models\User::whereIn('id', $studentIds)->get();
                \Illuminate\Support\Facades\Notification::send(
                    $students,
                    new \App\Notifications\FeeDemandPublishedToStudent($demand, $batch->title)
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Notify students of new fee demand failed', [
                'demand_id' => $demand->id, 'err' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('instructor.fees.index')
            ->with([
                'messege'    => __('Fee demand “:title” created.', ['title' => $demand->title]),
                'alert-type' => 'success',
            ]);
    }

    /**
     * GET /instructor/fees/transactions — payments list.
     */
    public function transactions(Request $request)
    {
        $coachId        = $this->coachId();
        $teacherBatches = $this->teacherBatchScope();

        // Only payments whose parent demand belongs to this coach AND
        // (for teachers) whose batch is in the teacher's allowed list.
        $demandIds = FeeDemand::forCoach($coachId)
            ->when($teacherBatches !== null, fn ($q) => $q->whereIn('batch_id', $teacherBatches ?: [0]))
            ->select('id');

        $query = FeePayment::query()
            ->whereIn('fee_demand_id', $demandIds)
            ->with(['student:id,name,email', 'demand:id,title,coach_id,batch_id', 'demand.batch:id,title']);

        // Optional filters.
        if ($request->filled('status') && in_array($request->status, ['paid', 'failed', 'refunded', 'initiated'], true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('q')) {
            $q = '%' . trim($request->q) . '%';
            $query->whereHas('student', function ($qq) use ($q) {
                $qq->where('name', 'like', $q)->orWhere('email', 'like', $q);
            });
        }

        $payments = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $kpi      = $this->kpiSnapshot($coachId);

        return view('frontend.instructor-dashboard.fees.transactions', compact(
            'payments', 'kpi'
        ));
    }

    /**
     * GET /instructor/fees/demands/{demand}/students — JSON list of the
     * demand's batch students with how much each still owes on THIS demand.
     * Powers the "Record Offline Payment" modal: the student picker + the
     * amount prefill (outstanding = demand amount − already-paid). Coach /
     * teacher scoped + permission-gated exactly like recordPayment, so a
     * staff member can't enumerate students of an unassigned batch.
     */
    public function studentsForDemand(int $demandId)
    {
        $coachId = $this->coachId();
        $demand = FeeDemand::forCoach($coachId)->findOrFail($demandId);
        $this->enforceTeacherCanAccessBatch((int) $demand->batch_id);

        $studentIds = Enrollment::where('batch_id', $demand->batch_id)
            ->pluck('user_id')->unique();

        $students = \App\Models\User::whereIn('id', $studentIds)
            ->where('role', 'student')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Paid-so-far per student on THIS demand.
        $paidByStudent = FeePayment::where('fee_demand_id', $demand->id)
            ->where('status', 'paid')
            ->select('student_id', DB::raw('SUM(amount) as paid'))
            ->groupBy('student_id')
            ->pluck('paid', 'student_id');

        $amount = (float) $demand->amount;
        $rows = $students->map(function ($s) use ($paidByStudent, $amount) {
            $paid = (float) ($paidByStudent[$s->id] ?? 0);
            return [
                'id'          => (int) $s->id,
                'name'        => $s->name,
                'email'       => $s->email,
                'paid'        => round($paid, 2),
                'outstanding' => round(max(0, $amount - $paid), 2),
            ];
        })->values();

        return response()->json(['amount' => round($amount, 2), 'students' => $rows]);
    }

    /**
     * Record a manual / offline payment against a demand (cash, cheque,
     * "marked paid"). Razorpay-mediated payments will land here via
     * the gateway webhook in a separate phase.
     */
    public function recordPayment(Request $request, int $demandId)
    {
        $coachId = $this->coachId();
        $demand = FeeDemand::forCoach($coachId)->findOrFail($demandId);
        // 2026-05-20 P4 — teacher must be granted on the demand's batch.
        $this->enforceTeacherCanAccessBatch((int) $demand->batch_id);

        $rules = [
            'student_id'   => 'required|exists:users,id',
            'amount'       => 'required|numeric|min:0.01',
            // 2026-07-10 — 'upi' added for the offline-payment UI.
            'gateway'      => 'nullable|in:manual,cash,cheque,bank_transfer,upi,other',
            // Coach can back-date an offline receipt (money received earlier).
            'paid_at'      => 'nullable|date',
            // Cheque no. / UPI txn id / bank ref.
            'reference_no' => 'nullable|string|max:128',
            'note'         => 'nullable|string|max:500',
            // 2026-07-11 — optional proof/receipt upload (Offline Payment upgrade).
            'proof'        => 'nullable|file|max:8192|mimes:pdf,jpg,jpeg,png,webp',
        ];
        $request->validate($rules);

        // A payment can't be received in the future — clamp a stray future date
        // to now rather than rejecting (kinder to the coach; DB tz is IST while
        // the app runs UTC, so an exact-boundary "today" would false-reject).
        $paidAt = $request->filled('paid_at')
            ? \Illuminate\Support\Carbon::parse($request->paid_at)
            : now();
        if ($paidAt->isFuture()) {
            $paidAt = now();
        }

        // Verify the student is actually in the demand's batch.
        // 2026-06-02 (fee-visibility fix) — membership is the batch enrollment
        // row, NOT has_access=1. A coach records a CASH payment precisely for a
        // member who hasn't paid yet (has_access=0, access granted after pay);
        // requiring has_access=1 here blocked recording the very payment that
        // unlocks access. Matches the student My Fees / checkout fix.
        $enrolled = Enrollment::where('user_id', $request->student_id)
            ->where('batch_id', $demand->batch_id)
            ->exists();
        if (!$enrolled) {
            return back()->withErrors(['student_id' => __('Student is not enrolled in this batch.')]);
        }

        // 2026-07-11 — Offline Payment upgrade. Route the fee collection through
        // the shared OfflinePaymentService so it gains the unified audit trail,
        // optional proof upload and the per-coach approval gate. The service
        // writes the paid fee_payments row (identical shape to before) when the
        // payment is auto-approved; if the coach requires approval it is held
        // in the offline_payments ledger until approved. gateway→method maps
        // the legacy 'manual' selector onto 'cash'.
        $method = $request->gateway ?: 'cash';
        if ($method === 'manual') {
            $method = 'cash';
        }

        app(\App\Services\OfflinePaymentService::class)->record($coachId, [
            'student_id'   => (int) $request->student_id,
            'source_type'  => \App\Models\OfflinePayment::SOURCE_FEE,
            'source_id'    => $demand->id,
            'batch_id'     => $demand->batch_id,
            'amount'       => (float) $request->amount,
            'method'       => $method,
            'reference_no' => $request->reference_no,
            'paid_at'      => $paidAt->toDateTimeString(),
            'notes'        => $request->note,
            'status'       => 'paid',
            'currency'     => 'INR',
        ], $request->file('proof'));

        return back()->with([
            'messege'    => __('Payment recorded.'),
            'alert-type' => 'success',
        ]);
    }

    /**
     * Refund a paid payment.
     *
     * For Razorpay-mediated payments: calls the real Razorpay refund
     * API so the money actually leaves the merchant account, THEN
     * flips status to 'refunded'. If the gateway call fails, the
     * local row stays 'paid' so the coach can retry — a half-finished
     * refund is worse than a not-yet-attempted one.
     *
     * For manual / cash / cheque: just flips the local row.
     */
    public function refundPayment(int $paymentId)
    {
        $coachId = $this->coachId();
        $payment = FeePayment::with('demand:id,batch_id')
            ->whereIn('fee_demand_id',
                FeeDemand::forCoach($coachId)->select('id'))
            ->findOrFail($paymentId);
        // 2026-05-20 P4 — teacher refund requires the demand's batch
        // to be in their allowed list.
        $this->enforceTeacherCanAccessBatch((int) ($payment->demand?->batch_id ?? 0));

        if ($payment->status !== 'paid') {
            return back()->withErrors([
                'payment' => __('Only paid payments can be refunded.'),
            ]);
        }

        // Razorpay leg — call the gateway first.
        if ($payment->gateway === 'razorpay' && $payment->gateway_txn_id) {
            try {
                [$keyId, $keySecret] = $this->razorpayCredentials();
                if (!$keyId || !$keySecret) {
                    return back()->withErrors([
                        'payment' => __('Razorpay credentials missing — cannot refund through gateway.'),
                    ]);
                }
                $api = new \Razorpay\Api\Api($keyId, $keySecret);
                // gateway_txn_id was swapped to payment_id after verify();
                // if we still hold an order_id (rare path), the SDK will
                // throw a clear error which we surface below.
                $api->payment->fetch($payment->gateway_txn_id)->refund([
                    'amount' => (int) round(((float) $payment->amount) * 100),
                    'notes'  => [
                        'fee_payment_id' => (string) $payment->id,
                        'receipt_no'     => $payment->receipt_no,
                    ],
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Razorpay refund failed', [
                    'fee_payment_id' => $payment->id,
                    'err'            => $e->getMessage(),
                ]);
                return back()->withErrors([
                    'payment' => __('Razorpay refund failed: :err', ['err' => $e->getMessage()]),
                ]);
            }
        }

        $payment->update([
            'status'      => 'refunded',
            'refunded_at' => now(),
        ]);

        // Audit the refund (symmetric money-out trail) — mirrors the course-
        // order refund path. Actor = the coach / staff who issued it.
        \App\Services\ActivityLogger::log(
            \App\Models\ActivityLog::PAYMENT_STATUS_CHANGED,
            'fee',
            $payment,
            ['status' => 'paid'],
            [
                'status'  => 'refunded',
                'gateway' => $payment->gateway,
                'amount'  => $payment->amount,
            ],
            'Fee payment ' . $payment->receipt_no . ' refunded'
        );

        // Notify the student (coach-branded refund confirmation).
        try {
            $student = $payment->student ?? \App\Models\User::find($payment->student_id);
            if ($student) {
                $student->notify(new \App\Notifications\FeeRefundedToStudent($payment));
            }
        } catch (\Throwable $e) {
            \Log::warning('Fee-refund notify failed: ' . $e->getMessage());
        }

        return back()->with([
            'messege'    => __('Refund recorded for receipt :no.', ['no' => $payment->receipt_no]),
            'alert-type' => 'success',
        ]);
    }

    /**
     * Same lookup as StudentFeePaymentController::razorpayCredentials().
     * Kept private to this controller to avoid a circular helper class.
     */
    private function razorpayCredentials(): array
    {
        try {
            $kv = \DB::table('payment_gateways')
                ->whereIn('key', ['razorpay_key', 'razorpay_secret'])
                ->pluck('value', 'key')
                ->all();
            $decrypted = \App\Support\SecretSettings::decryptForTable('payment_gateways', $kv);
            $key    = $decrypted['razorpay_key']    ?? null;
            $secret = $decrypted['razorpay_secret'] ?? null;
            if (!$key || !$secret || $key === 'razorpay_key' || $secret === 'razorpay_secret') {
                return [null, null];
            }
            return [$key, $secret];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }

    /**
     * Headline KPI numbers for the dashboard banner.
     * Cached only by request lifetime; not memoised across requests
     * because numbers must be live for "Mark paid" actions to feel
     * responsive.
     */
    private function kpiSnapshot(int $coachId): array
    {
        $paymentsQ = FeePayment::query()
            ->whereIn('fee_demand_id', FeeDemand::forCoach($coachId)->select('id'));

        return [
            'total_collected' => (float) (clone $paymentsQ)->where('status', 'paid')->sum('amount'),
            'paid_count'      => (int) (clone $paymentsQ)->where('status', 'paid')->count(),
            'failed_count'    => (int) (clone $paymentsQ)->where('status', 'failed')->count(),
            'refunded_count'  => (int) (clone $paymentsQ)->where('status', 'refunded')->count(),
            'refunded_amount' => (float) (clone $paymentsQ)->where('status', 'refunded')->sum('amount'),
        ];
    }
}
