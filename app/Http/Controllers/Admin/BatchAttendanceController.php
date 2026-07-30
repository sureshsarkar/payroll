<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseBatch;
use App\Services\BatchAttendanceService;
use Illuminate\Http\Request;

/**
 * Admin-side batch attendance summary page.
 *
 * Audit 2026-05-18 — paired with BatchAttendanceService.
 *
 * Permissions: reuses dashboard.view (any admin who can see the
 * dashboard can see counters; tightening to a dedicated permission
 * if needed is one line — change checkAdminHasPermissionAndThrowException).
 */
class BatchAttendanceController extends Controller
{
    public function show(Request $request, int $batchId, BatchAttendanceService $svc)
    {
        checkAdminHasPermissionAndThrowException('dashboard.view');

        $batch = CourseBatch::with('course:id,title')->findOrFail($batchId);

        $date = $request->filled('date') ? $request->date : null;
        $summary = $svc->summaryFor($batch, $date);

        return view('admin.batches.attendance-summary', compact('batch', 'summary'));
    }

    /**
     * Audit 2026-05-18 — admin listing of all batches with per-batch
     * today-attendance row. One row per batch; click "View" → /admin/batch-attendance/{id}.
     */
    public function index(Request $request, BatchAttendanceService $svc)
    {
        checkAdminHasPermissionAndThrowException('dashboard.view');

        $query = CourseBatch::query()->with('course:id,title');

        if ($request->filled('q')) {
            $search = (string) $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('course', fn($c) => $c->where('title', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('status') && in_array($request->status, ['active','inactive'], true)) {
            $query->where('status', $request->status);
        }

        $batches = $query->orderByDesc('start_date')->paginate(20)->withQueryString();

        // Per-row counters (today only) — small batch sizes per page, OK.
        $summaries = $batches->mapWithKeys(fn ($b) => [$b->id => $svc->summaryFor($b, now())]);

        return view('admin.batches.index', compact('batches', 'summaries'));
    }
}
