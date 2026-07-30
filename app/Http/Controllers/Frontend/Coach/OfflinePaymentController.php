<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachBrandSetting;
use App\Models\CoachStudentLink;
use App\Models\Course;
use App\Models\OfflinePayment;
use App\Services\OfflinePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;

/**
 * Offline Payment — coach panel (Phase 1).
 *
 * Records payments received off-platform (cash / bank / UPI / cheque / other)
 * WITHOUT the online gateway. Every action is strictly scoped to the acting
 * coach (tenant): dropdowns list only the coach's own students/courses, and
 * every write/read of an offline_payments row goes through
 * OfflinePayment::forCoach() so one coach can never touch another's records,
 * receipts or proof files.
 */
class OfflinePaymentController extends Controller
{
    private string $pageName = 'offline-payments';

    public function __construct(private OfflinePaymentService $service)
    {
    }

    /** The acting coach's tenant id (self if coach, parent if staff). */
    private function coachId(): int
    {
        return userAuth()->role === 'instructor' ? (int) userAuth()->id : (int) userAuth()->coach_id;
    }

    /**
     * Student ids the coach may record for: the CoachStudentLink roster (added +
     * purchased + linked — same source as the dropdown) UNION any students they
     * directly own (added_by/coach_id). Never falsely rejects a legit student;
     * strictly tenant-scoped.
     */
    private function allowedStudentIds(int $coachId): array
    {
        $rosterIds = array_map('intval', (array) CoachStudentLink::studentIdsForCoach($coachId));
        $ownedIds  = \App\Models\User::where('role', 'student')
            ->where(fn ($q) => $q->where('added_by', $coachId)->orWhere('coach_id', $coachId))
            ->pluck('id')->map(fn ($v) => (int) $v)->all();

        return array_values(array_unique(array_merge($rosterIds, $ownedIds)));
    }

    /** Offline payments for a coach, filtered by the request's from/to (by paid_at). */
    private function rangedQuery(int $coachId, Request $request)
    {
        $q = OfflinePayment::forCoach($coachId);
        $from = $request->query('from');
        $to   = $request->query('to');
        if ($from && strtotime($from)) {
            $q->whereDate('paid_at', '>=', date('Y-m-d', strtotime($from)));
        }
        if ($to && strtotime($to)) {
            $q->whereDate('paid_at', '<=', date('Y-m-d', strtotime($to)));
        }
        return $q;
    }

    /** Collected (effective) summary for the current range — totals + by method. */
    private function collectionSummary(int $coachId, Request $request): array
    {
        $eff = $this->rangedQuery($coachId, $request)->active()
            ->whereIn('approval_status', [OfflinePayment::APPROVAL_AUTO, OfflinePayment::APPROVAL_APPROVED]);

        return [
            'count'     => (clone $eff)->count(),
            'total'     => (float) (clone $eff)->sum('amount'),
            'tax'       => (float) (clone $eff)->sum('tax_amount'),
            'pending'   => (int) $this->rangedQuery($coachId, $request)
                ->where('approval_status', OfflinePayment::APPROVAL_PENDING)->count(),
            'by_method' => (clone $eff)->selectRaw('method, COUNT(*) as c, SUM(amount) as s')
                ->groupBy('method')->orderByDesc('s')->get(),
        ];
    }

    /**
     * Stream the offline-payments ledger (current filter) as CSV for reconciliation.
     * Tenant-scoped; chunked so a large ledger never OOMs.
     */
    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_if(checkPermission($this->pageName) != 1, 403);
        $coachId = $this->coachId();
        $query = $this->rangedQuery($coachId, $request)
            ->with(['student', 'course', 'recorder'])->orderByDesc('id');

        $filename = 'offline-payments-' . date('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Receipt', 'Date', 'Student', 'For', 'Method', 'Reference',
                'Amount', 'Base', 'Tax', 'Status', 'Approval', 'Recorded by']);
            $query->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $p) {
                    fputcsv($out, [
                        $p->receipt_no,
                        optional($p->paid_at)->format('Y-m-d'),
                        $p->student->name ?? '',
                        $p->course->title ?: 'Fee / other',
                        $p->methodLabel(),
                        $p->reference_no,
                        number_format((float) $p->amount, 2, '.', ''),
                        $p->base_amount !== null ? number_format((float) $p->base_amount, 2, '.', '') : '',
                        $p->tax_amount !== null ? number_format((float) $p->tax_amount, 2, '.', '') : '',
                        $p->cancelled_at ? 'cancelled' : $p->status,
                        $p->approval_status,
                        $p->recorder->name ?? '',
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function index(Request $request)
    {
        if (checkPermission($this->pageName) != 1) {
            return view("errors.403");
        }
        $coachId = $this->coachId();

        // Tenant-scoped pickers (roster ∪ directly-owned — see allowedStudentIds).
        $students = \App\Models\User::whereIn('id', $this->allowedStudentIds($coachId) ?: [0])
            ->where('role', 'student')->orderBy('name')->get(['id', 'name', 'email']);

        $courses = Course::where(function ($q) use ($coachId) {
            $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
        })->orderBy('title')->get(['id', 'title', 'type']);

        // All active batches under those courses; the form filters by the
        // selected course client-side (no extra request, stays tenant-bounded).
        $batches = \App\Models\CourseBatch::whereIn('course_id', $courses->pluck('id') ?: [0])
            ->where('status', 'active')->orderBy('title')->get(['id', 'title', 'course_id']);

        // The coach's enabled methods + approval preference (white-label config).
        $brand = CoachBrandSetting::firstOrCreateForCoach($coachId);
        $needsApproval  = (bool) $brand->offline_payment_needs_approval;
        $emailReceipt   = (bool) $brand->offline_payment_email_receipt;
        $enabledMethods = OfflinePayment::enabledMethodsForCoach($coachId); // key[]
        $allMethods     = OfflinePayment::METHOD_META;                      // key => [label, icon]

        // Report — collected summary for the current date range (+ the list, ranged).
        $summary = $this->collectionSummary($coachId, $request);
        $from = (string) $request->query('from', '');
        $to   = (string) $request->query('to', '');

        $payments = $this->rangedQuery($coachId, $request)
            ->with(['student', 'course', 'recorder'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('frontend.instructor-dashboard.offline-payments.index', compact(
            'students', 'courses', 'batches', 'payments', 'needsApproval', 'emailReceipt',
            'enabledMethods', 'allMethods', 'summary', 'from', 'to'
        ));
    }

    /**
     * Save the coach's Offline Payment preferences: which methods they accept
     * and whether recorded payments need approval. Tenant-scoped to the acting
     * coach. Empty selection falls back to all methods (a form never shows none).
     */
    public function updateSettings(Request $request)
    {
        if (checkPermission($this->pageName) != 1) {
            return view("errors.403");
        }
        $coachId = $this->coachId();

        $validated = $request->validate([
            'methods'          => 'nullable|array',
            'methods.*'        => 'in:cash,bank_transfer,upi,cheque,other',
            'needs_approval'   => 'nullable|boolean',
            'email_receipt'    => 'nullable|boolean',
        ]);

        $methods = array_values(array_intersect($validated['methods'] ?? [], OfflinePayment::METHODS));

        CoachBrandSetting::firstOrCreateForCoach($coachId)->forceFill([
            'offline_payment_methods'         => $methods ?: null, // null = all
            'offline_payment_needs_approval'  => (bool) $request->boolean('needs_approval'),
            'offline_payment_email_receipt'   => (bool) $request->boolean('email_receipt'),
        ])->save();

        return back()->with(['messege' => __('Offline payment settings saved.'), 'alert-type' => 'success']);
    }

    /**
     * Record an OFFLINE COURSE PURCHASE in one step: create the (pending) order,
     * then the service records the ledger row and — when auto-approved and marked
     * paid — routes through the proven markPaid() path to enroll the student and
     * credit commission. No gateway is called.
     */
    public function storeCourse(Request $request)
    {
        if (checkPermission($this->pageName) != 1) {
            return view("errors.403");
        }
        $coachId = $this->coachId();

        // 2026-07-11 fix — validate against the roster (CoachStudentLink) UNION
        // directly-owned students, the SAME set the dropdown shows. A purchase-
        // linked student is on the roster but not "added_by" the coach, so the old
        // column-only check wrongly rejected them ("student is not yours").
        $validated = $request->validate([
            'student_id' => [
                'required', 'integer',
                \Illuminate\Validation\Rule::in($this->allowedStudentIds($coachId)),
            ],
            'course_id' => [
                'required', 'integer',
                \Illuminate\Validation\Rule::exists('courses', 'id')->where(function ($q) use ($coachId) {
                    $q->where(fn ($w) => $w->where('added_by', $coachId)->orWhere('instructor_id', $coachId));
                }),
            ],
            'batch_id' => [
                'nullable', 'integer',
                \Illuminate\Validation\Rule::exists('course_batches', 'id')->where(function ($q) use ($coachId) {
                    $q->whereExists(function ($sub) use ($coachId) {
                        $sub->select(DB::raw(1))->from('courses')
                            ->whereColumn('courses.id', 'course_batches.course_id')
                            ->where(fn ($cq) => $cq->where('added_by', $coachId)->orWhere('instructor_id', $coachId));
                    });
                }),
            ],
            'amount'       => 'required|numeric|min:0.01',
            'method'       => ['required', \Illuminate\Validation\Rule::in(OfflinePayment::enabledMethodsForCoach($coachId))],
            'reference_no' => 'nullable|string|max:128',
            'paid_at'      => 'nullable|date',
            'notes'        => 'nullable|string|max:1000',
            'status'       => 'nullable|in:paid,partial,pending',
            'proof'        => 'nullable|file|max:8192|mimes:pdf,jpg,jpeg,png,webp',
        ], [
            'student_id.in'     => __('Selected student is not on your roster'),
            'course_id.exists'  => __('Selected course is not yours'),
            'batch_id.exists'   => __('Selected batch is not yours'),
        ]);

        $amount = (float) $validated['amount'];
        $commissionRate = (float) (Cache::get('setting')?->commission_rate ?? 0);

        $order = DB::transaction(function () use ($validated, $coachId, $amount, $commissionRate) {
            // Offline receipts record the EXACT amount received; no separate tax
            // line is split here (the coach enters the final figure).
            $order = Order::create([
                'invoice_id'          => Str::random(10),
                'buyer_id'            => (int) $validated['student_id'],
                'seller_id'           => $coachId,
                'has_coupon'          => 0,
                'coupon_code'         => '',
                'coupon_discount_amount' => 0,
                'payment_method'      => 'offline_' . $validated['method'],
                'payment_status'      => 'pending',   // becomes paid via the service
                'status'              => 'pending',
                'payable_amount'      => $amount,
                'tax_amount'          => 0,
                'taxable_amount'      => $amount,
                'gateway_charge'      => 0,
                'payable_with_charge' => $amount,
                'paid_amount'         => $amount,
                'payable_currency'    => 'INR',
                'conversion_rate'     => 1,
                'commission_rate'     => $commissionRate,
                'order_type'          => 'course',
                'order_details'       => 'Offline payment recorded by coach',
                'transaction_id'      => 'OFFLINE-' . Str::random(12),
            ]);
            OrderItem::create([
                'order_id'        => $order->id,
                'price'           => $amount,
                'course_id'       => (int) $validated['course_id'],
                'batch_id'        => $validated['batch_id'] ?? null,
                'commission_rate' => $commissionRate,
            ]);
            return $order;
        });

        $this->service->record($coachId, [
            'student_id'   => (int) $validated['student_id'],
            'source_type'  => OfflinePayment::SOURCE_ORDER,
            'order_id'     => $order->id,
            'course_id'    => (int) $validated['course_id'],
            'batch_id'     => $validated['batch_id'] ?? null,
            'amount'       => $amount,
            'method'       => $validated['method'],
            'reference_no' => $validated['reference_no'] ?? null,
            'paid_at'      => $validated['paid_at'] ?? null,
            'notes'        => $validated['notes'] ?? null,
            'status'       => $validated['status'] ?? 'paid',
            'currency'     => 'INR',
        ], $request->file('proof'));

        return back()->with([
            'messege'    => __('Offline payment recorded.'),
            'alert-type' => 'success',
        ]);
    }

    /**
     * Record how an EXISTING (pending) order was paid offline. The order was
     * already created (e.g. via Manual Sale); this captures method / reference /
     * date / proof and marks it paid — enrolling the student through the same
     * idempotent markPaid() path. Tenant-safe: the order must contain an item in
     * one of the coach's own courses.
     */
    public function recordForOrder(Request $request, int $orderId)
    {
        if (checkPermission($this->pageName) != 1) {
            return view("errors.403");
        }
        $coachId = $this->coachId();
        $order = $this->coachOwnedOrder($orderId);
        abort_if($order->payment_status === 'paid', 400, __('This order is already paid.'));

        $validated = $request->validate([
            'method'       => ['required', \Illuminate\Validation\Rule::in(OfflinePayment::enabledMethodsForCoach($coachId))],
            'reference_no' => 'nullable|string|max:128',
            'paid_at'      => 'nullable|date',
            'notes'        => 'nullable|string|max:1000',
            'proof'        => 'nullable|file|max:8192|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        $item = $order->orderItems->first();
        $this->service->record($coachId, [
            'student_id'   => (int) $order->buyer_id,
            'source_type'  => OfflinePayment::SOURCE_ORDER,
            'order_id'     => $order->id,
            'course_id'    => $item?->course_id,
            'batch_id'     => $item?->batch_id,
            'amount'       => (float) $order->paid_amount,
            'method'       => $validated['method'],
            'reference_no' => $validated['reference_no'] ?? null,
            'paid_at'      => $validated['paid_at'] ?? null,
            'notes'        => $validated['notes'] ?? null,
            'status'       => 'paid',
            'currency'     => $order->payable_currency ?? 'INR',
        ], $request->file('proof'));

        return back()->with(['messege' => __('Offline payment recorded for this order.'), 'alert-type' => 'success']);
    }

    /** Resolve an order the acting coach owns (has an item in their course). */
    private function coachOwnedOrder(int $orderId): Order
    {
        $coachId = $this->coachId();
        $courseIds = Course::where(function ($q) use ($coachId) {
            $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
        })->pluck('id');

        return Order::with('orderItems')
            ->whereHas('orderItems', fn ($q) => $q->whereIn('course_id', $courseIds ?: [0]))
            ->findOrFail($orderId);
    }

    /**
     * Record an offline payment for a TRIAL / 1:1 enquiry. A trial payer is a
     * lead (email) until paid, so we provision the student first (creates/links
     * a coach-scoped student account) to obtain a user id, then record + settle
     * the trial. Gated by the trial-sessions permission (a real coach passes).
     */
    public function recordForTrial(Request $request, int $enquiryId)
    {
        if (checkPermission($this->pageName) != 1) {
            return view("errors.403");
        }
        $coachId = $this->coachId();
        $enquiry = \App\Models\CoachTrialEnquiry::where('coach_id', $coachId)->findOrFail($enquiryId);
        abort_if($enquiry->payment_status === \App\Models\CoachTrialEnquiry::PAY_PAID, 400, __('This trial is already paid.'));

        $validated = $request->validate([
            'method'       => ['required', \Illuminate\Validation\Rule::in(OfflinePayment::enabledMethodsForCoach($coachId))],
            'reference_no' => 'nullable|string|max:128',
            'paid_at'      => 'nullable|date',
            'notes'        => 'nullable|string|max:1000',
            'amount'       => 'nullable|numeric|min:0',
            'proof'        => 'nullable|file|max:8192|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        // Provision (create/link) the student → gives us a real users.id.
        $prov = app(\App\Services\TrialStudentProvisioner::class)->provision($enquiry);
        $student = $prov['user'] ?? null;
        abort_if(! $student, 422, __('Could not resolve a student for this trial (the email is missing or belongs to a non-student account).'));
        $enquiry->refresh();

        $this->service->record($coachId, [
            'student_id'   => (int) $student->id,
            'source_type'  => OfflinePayment::SOURCE_TRIAL,
            'source_id'    => $enquiry->id,
            'amount'       => (float) ($validated['amount'] ?? $enquiry->price),
            'method'       => $validated['method'],
            'reference_no' => $validated['reference_no'] ?? null,
            'paid_at'      => $validated['paid_at'] ?? null,
            'notes'        => $validated['notes'] ?? null,
            'status'       => 'paid',
            'currency'     => $enquiry->currency ?: 'INR',
        ], $request->file('proof'));

        return back()->with(['messege' => __('Offline trial payment recorded.'), 'alert-type' => 'success']);
    }

    /* ───────── approval workflow (tenant-gated) ───────── */

    public function approve(int $id)
    {
        $op = OfflinePayment::forCoach($this->coachId())->findOrFail($id);
        $this->service->approve($op);
        return back()->with(['messege' => __('Payment approved.'), 'alert-type' => 'success']);
    }

    public function reject(Request $request, int $id)
    {
        $op = OfflinePayment::forCoach($this->coachId())->findOrFail($id);
        $this->service->reject($op, $request->input('reason'));
        return back()->with(['messege' => __('Payment rejected.'), 'alert-type' => 'success']);
    }

    public function cancel(Request $request, int $id)
    {
        $op = OfflinePayment::forCoach($this->coachId())->findOrFail($id);
        $this->service->cancel($op, $request->input('reason'));
        return back()->with(['messege' => __('Payment cancelled.'), 'alert-type' => 'success']);
    }

    /* ───────── gated proof download (tenant-checked) ───────── */

    public function downloadProof(int $id)
    {
        $op = OfflinePayment::forCoach($this->coachId())->findOrFail($id);
        abort_unless($op->proof_path && Storage::disk('private')->exists($op->proof_path), 404);
        return Storage::disk('private')->download($op->proof_path, $op->proof_name ?: 'proof');
    }

    /* ───────── coach-branded receipt ───────── */

    public function receipt(int $id)
    {
        $op = OfflinePayment::forCoach($this->coachId())
            ->with(['student', 'course'])
            ->findOrFail($id);
        $brand = app(\App\Services\BrandResolver::class)->forCoach($op->coach_id);

        return view('frontend.instructor-dashboard.offline-payments.receipt', compact('op', 'brand'));
    }

    /**
     * Server-generated, coach-branded PDF receipt (DomPDF). Tenant-checked. Falls
     * back to the printable HTML receipt if PDF generation ever fails.
     */
    public function receiptPdf(int $id)
    {
        $op = OfflinePayment::forCoach($this->coachId())
            ->with(['student', 'course', 'recorder', 'approver'])
            ->findOrFail($id);
        $brand = app(\App\Services\BrandResolver::class)->forCoach($op->coach_id);
        try {
            getSessionCurrency();
        } catch (\Throwable $e) {
        }
        $curCode = session('currency_code') ?: 'INR';

        try {
            $html = view('frontend.instructor-dashboard.offline-payments.receipt-pdf', compact('op', 'brand', 'curCode'))->render();
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return response($dompdf->output(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="receipt-' . $op->receipt_no . '.pdf"',
            ]);
        } catch (\Throwable $e) {
            \Log::warning('offline-receipt-pdf-failed', ['id' => $op->id, 'err' => $e->getMessage()]);
            return redirect()->route('instructor.offline-payments.receipt', $op->id);
        }
    }
}
