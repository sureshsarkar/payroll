<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Order\app\Models\Order;

class StudentOrderController extends Controller
{
    function index() {
        // CORRECTNESS (audit 2026-05-22) — the original computed two counts
        // by re-using the SAME cloned query: the second `->where('status',
        // 'pending')` chained ON TOP of the previous `->where('status',
        // 'completed')` because $baseQueryOrder is a builder, not a snapshot.
        // Result: pendingCount silently == 0 because status can't be both
        // 'completed' AND 'pending' at the same time.
        //
        // Fix: collapse to a single aggregate query — 1 round-trip, 2 correct
        // counts, no clone bugs.
        $userId = userAuth()->id;

        // 2026-06-09 — TENANT SCOPE: on a coach domain show only orders that
        // contain THIS coach's courses. Null/0 on the platform → unchanged.
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        $coachScope = fn ($q) => $q->when($tenantCoachId > 0, fn ($x) =>
            $x->whereHas('orderItems.course', fn ($c) => $c->where('instructor_id', $tenantCoachId)));

        $statusCounts = Order::where('buyer_id', $userId)
            ->tap($coachScope)
            ->selectRaw("
                COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_count,
                COUNT(CASE WHEN status = 'pending'   THEN 1 END) AS pending_count
            ")
            ->first();
        $completedCount = (int) ($statusCounts->completed_count ?? 0);
        $pendingCount   = (int) ($statusCounts->pending_count   ?? 0);

        $orders = Order::with(['orderItems', 'orderItems.course' => function ($q) {
                $q->select('id', 'title');
            }])
            ->where('buyer_id', $userId)
            ->tap($coachScope)
            ->orderBy('id', 'desc')
            ->paginate(10);

        return view('frontend.student-dashboard.order.index', compact('orders', 'completedCount', 'pendingCount'));
    }

    function show(string $id) {
        $order = $this->ownedOrder($id);
        return view('frontend.student-dashboard.order.show', compact('order'));
    }

    function printInvoice( Request $request, $id) {
        $order = $this->ownedOrder($id);
       return view('frontend.student-dashboard.order.invoice', compact('order'));
    }

    /**
     * Fetch an order owned by the current student, and — on a coach domain —
     * only if it contains THIS coach's course (so coach B's domain can't open
     * an order that's really coach A's). 404 otherwise.
     */
    private function ownedOrder(string $id): Order
    {
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        return Order::where('id', $id)
            ->where('buyer_id', userAuth()->id)
            ->when($tenantCoachId > 0, fn ($q) =>
                $q->whereHas('orderItems.course', fn ($c) => $c->where('instructor_id', $tenantCoachId)))
            ->firstOrFail();
    }
}
