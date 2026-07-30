<?php

namespace App\Http\Controllers\Frontend;

use App\Exceptions\AccessPermissionDeniedException;
use App\Http\Controllers\Controller;
use App\Models\CourseBatch;
use App\Models\CourseLiveClass;
use App\Models\LiveClassAttendance;
use App\Services\BatchAttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Modules\Order\app\Models\Enrollment;

/**
 * Coach-side per-batch attendance.
 *
 * Audit 2026-05-18 phase 2 — extended from summary cards into a full
 * dashboard:
 *   - summary cards (Total / Attended / Not Attended)
 *   - 7-day attendance trend (mini bar chart)
 *   - per-student roster (filterable, sortable)
 *   - bulk manual attendance marking
 *   - CSV export
 *
 * Ownership: coach must own the course the batch belongs to; staff
 * users inherit via coach_id.
 */
class CoachBatchAttendanceController extends Controller
{
    public function show(Request $request, int $batch, BatchAttendanceService $svc)
    {
        $batchModel = $this->authoriseBatch($batch);

        $date = $request->filled('date') ? $request->date : null;
        $summary = $svc->summaryFor($batchModel, $date);
        $roster  = $svc->studentRoster($batchModel, $date);
        $trend   = $this->sevenDayTrend($batchModel, $date, $svc);

        // Optional roster filter. Audit 2026-05-18 phase 4 — 'attended'
        // now means verified (verification_status === 'verified');
        // 'absent' covers absent + partial + pending.
        $statusFilter = $request->query('attendance');
        if ($statusFilter === 'attended') {
            $roster = $roster->where('verification_status', 'verified')->values();
        } elseif ($statusFilter === 'absent') {
            $roster = $roster->whereIn('verification_status', ['absent', 'partial', 'pending'])->values();
        }

        return view('frontend.instructor-dashboard.batch-attendance.show', [
            'batch'        => $batchModel,
            'summary'      => $summary,
            'roster'       => $roster,
            'trend'        => $trend,
            'statusFilter' => $statusFilter,
        ]);
    }

    /**
     * POST bulk manual attendance. Audit 2026-05-18 phase 2.
     * Body:
     *   action     => 'mark_attended' | 'unmark'
     *   user_ids[] => array of student ids to update
     *   date       => Y-m-d (defaults to today)
     *
     * For "mark_attended": creates a live_class_attendances row with
     * is_manual=1 against a live class scheduled for that date (or
     * creates a placeholder live_class for offline make-up sessions).
     *
     * For "unmark": deletes manual rows (is_manual=1) for those users
     * on that date. Auto-tracked rows (from real Zoom joins) are NOT
     * deleted — only manual additions are reversible.
     */
    public function bulkMark(Request $request, int $batch)
    {
        $batchModel = $this->authoriseBatch($batch);

        $request->validate([
            'action'       => 'required|in:mark_attended,unmark',
            'user_ids'     => 'required|array|min:1',
            'user_ids.*'   => 'integer',
            'date'         => 'nullable|date',
            'reason'       => 'nullable|string|max:255',
        ]);

        $date = $request->filled('date')
            ? CarbonImmutable::parse($request->date)
            : CarbonImmutable::today();

        $userIds = collect($request->user_ids)
            ->map(fn ($i) => (int) $i)
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Only allow updating students who are actually enrolled in this batch.
        // 2026-06-02 — membership is the batch enrollment row, NOT has_access=1.
        // A student attending while their fee is pending (has_access=0) must be
        // markable. Matches studentRoster() + the fee-visibility fix.
        $eligibleUserIds = Enrollment::where('batch_id', $batchModel->id)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->unique()
            ->all();

        if (empty($eligibleUserIds)) {
            return response()->json([
                'status' => 'error',
                'message' => __('No matching enrolled students.'),
            ], 422);
        }

        $reason = (string) ($request->reason ?: __('Manually marked by coach'));
        $coachUserId = (int) userAuth()->id;

        if ($request->action === 'mark_attended') {
            $liveClassId = $this->liveClassForDate($batchModel, $date, $coachUserId);
            $now = now();

            $created = 0;
            foreach ($eligibleUserIds as $uid) {
                // Skip if a manual row already exists for this (class, user)
                $exists = LiveClassAttendance::where('course_live_class_id', $liveClassId)
                    ->where('user_id', $uid)
                    ->where('is_manual', 1)
                    ->whereDate('joined_at', $date->toDateString())
                    ->exists();
                if ($exists) continue;

                LiveClassAttendance::create([
                    'course_live_class_id' => $liveClassId,
                    'user_id'              => $uid,
                    'role'                 => 'student',
                    'joined_at'            => $date->copy()->setTime(now()->hour, now()->minute, 0),
                    'left_at'              => null,
                    'duration_seconds'     => null,
                    'is_manual'            => 1,
                    'manual_reason'        => $reason,
                    'marked_by'            => $coachUserId,
                    // Audit 2026-05-18 phase 4 — coach's word is final. Manual
                    // marks bypass the duration threshold and are immediately
                    // verified.
                    'attendance_verified'  => 1,
                    'verified_at'          => now(),
                ]);
                $created++;
            }

            return response()->json([
                'status'  => 'success',
                'message' => trans_choice(':count student marked attended|:count students marked attended', $created, ['count' => $created]),
                'created' => $created,
            ]);
        }

        // action = unmark
        $deleted = LiveClassAttendance::whereHas('liveClass', function ($q) use ($batchModel) {
                $q->where('batch_id', $batchModel->id);
            })
            ->whereIn('user_id', $eligibleUserIds)
            ->where('is_manual', 1)
            ->whereDate('joined_at', $date->toDateString())
            ->delete();

        return response()->json([
            'status'  => 'success',
            'message' => trans_choice(':count manual mark removed|:count manual marks removed', $deleted, ['count' => $deleted]),
            'deleted' => $deleted,
        ]);
    }

    /**
     * CSV export of attendance for a date range.
     * GET /instructor/batch-attendance/{batch}/export?from=Y-m-d&to=Y-m-d
     */
    public function exportCsv(Request $request, int $batch, BatchAttendanceService $svc)
    {
        $batchModel = $this->authoriseBatch($batch);

        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->from)
            : CarbonImmutable::today()->subDays(6);
        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->to)
            : CarbonImmutable::today();

        if ($to->lessThan($from)) {
            return back()->withErrors(['date' => 'Invalid date range']);
        }

        $filename = sprintf(
            'attendance_batch%d_%s_to_%s.csv',
            $batchModel->id,
            $from->toDateString(),
            $to->toDateString()
        );

        $callback = function () use ($batchModel, $from, $to, $svc) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Batch', $batchModel->title]);
            fputcsv($handle, ['Course', optional($batchModel->course)->title ?? '']);
            fputcsv($handle, ['Range', $from->toDateString().' to '.$to->toDateString()]);
            fputcsv($handle, []);

            // Header row with one column per date
            $header = ['Student', 'Email'];
            $dates = [];
            for ($d = $from; $d->lessThanOrEqualTo($to); $d = $d->addDay()) {
                $header[] = $d->toDateString();
                $dates[] = $d;
            }
            $header[] = 'Total Days Attended';
            fputcsv($handle, $header);

            // Build per-student per-date map
            $dailyRosters = [];
            foreach ($dates as $d) {
                $dailyRosters[$d->toDateString()] = $svc->studentRoster($batchModel, $d)
                    ->keyBy('user_id');
            }

            // Use the first day's roster as the canonical student list
            $studentList = $dailyRosters[$from->toDateString()] ?? collect([]);

            foreach ($studentList as $stu) {
                $row = [$stu['name'], $stu['email']];
                $attendedCount = 0;
                foreach ($dates as $d) {
                    $dayRoster = $dailyRosters[$d->toDateString()] ?? collect([]);
                    $r = $dayRoster->get($stu['user_id']);
                    if ($r && $r['attended']) {
                        $row[] = $r['is_manual'] ? 'M' : 'Y';   // M=manual, Y=auto
                        $attendedCount++;
                    } else {
                        $row[] = '';   // empty = absent
                    }
                }
                $row[] = $attendedCount;
                fputcsv($handle, $row);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * 2026-06-02 (implemented 2026-07-15) — bulk "assign course-enrolled students
     * INTO this batch". Pulls batch-less students in, or MOVES students from
     * another batch of the SAME course. Tenant-gated (throws on a cross-coach
     * batch), never invents an enrollment for a non-course student, and reuses
     * StudentBatchService so the ledger/audit/notify path is identical to the
     * student-panel reassign flow. Reason is optional here (roster pull).
     */
    public function assignStudents(Request $request, int $batch, ?\App\Services\StudentBatchService $svc = null)
    {
        $svc ??= app(\App\Services\StudentBatchService::class);
        $batchModel = $this->authoriseBatch($batch);   // throws AccessPermissionDeniedException for a cross-coach batch

        $data = $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'integer',
            'reason'     => 'nullable|string|max:500',
        ]);

        $user      = userAuth();
        $coachId   = $user->role === 'instructor' ? (int) $user->id : (int) $user->coach_id;
        $changedBy = (int) $user->id;
        $reason    = $data['reason'] ?? null;

        $assigned = 0;
        $failed   = [];
        foreach (array_unique(array_map('intval', $data['user_ids'])) as $uid) {
            $res = $svc->putIntoBatch($coachId, $uid, $batchModel, $reason, $changedBy);
            if (! empty($res['ok'])) {
                $assigned++;
            } else {
                $failed[] = ['id' => $uid, 'message' => $res['message'] ?? __('Could not assign.')];
            }
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'assigned' => $assigned, 'failed' => $failed,
                'message' => trans_choice(':count student assigned.|:count students assigned.', $assigned, ['count' => $assigned])]);
        }

        return redirect()->back()->with([
            'messege'    => trans_choice(':count student assigned.|:count students assigned.', $assigned, ['count' => $assigned])
                            . ($failed ? ' ' . count($failed) . ' ' . __('skipped.') : ''),
            'alert-type' => $failed ? 'warning' : 'success',
        ]);
    }

    /* -------------------- helpers -------------------- */

    private function authoriseBatch(int $batch): CourseBatch
    {
        $user = userAuth();
        $coachId = $user->role === 'instructor' ? $user->id : $user->coach_id;

        $batchModel = CourseBatch::with('course:id,title,instructor_id,added_by')->findOrFail($batch);

        if (!$batchModel->course
            || ($batchModel->course->instructor_id != $coachId
                && $batchModel->course->added_by != $coachId)) {
            throw new AccessPermissionDeniedException();
        }

        return $batchModel;
    }

    /**
     * 7-day attendance trend ending on $endDate.
     *
     * @return array<int, array{date: string, attended: int, total: int, percent: float}>
     */
    private function sevenDayTrend(CourseBatch $batch, ?string $endDate, BatchAttendanceService $svc): array
    {
        $end = $endDate ? CarbonImmutable::parse($endDate) : CarbonImmutable::today();
        $start = $end->subDays(6);
        $total = $batch->studentCount();

        $rows = [];
        for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
            $attended = $batch->attendedOn($d->toDateString());
            $rows[] = [
                'date'     => $d->toDateString(),
                'label'    => $d->format('M j'),
                'attended' => $attended,
                'total'    => $total,
                'percent'  => $total > 0 ? round(($attended / $total) * 100, 1) : 0,
            ];
        }
        return $rows;
    }

    /**
     * Find a live_class for $batch on $date. If none exists, create a
     * placeholder "manual session" record so manual attendance entries
     * have something to FK against. Idempotent — re-use existing row.
     */
    private function liveClassForDate(CourseBatch $batch, CarbonImmutable $date, int $coachUserId): int
    {
        $existing = CourseLiveClass::where('batch_id', $batch->id)
            ->whereRaw('LEFT(start_time, 10) = ?', [$date->toDateString()])
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        // Need a lesson_id (NOT NULL FK). Pick the latest lesson of this course.
        $lessonId = \DB::table('course_chapter_lessons')
            ->where('course_id', $batch->course_id)
            ->orderByDesc('id')
            ->value('id');

        if (!$lessonId) {
            // As a fallback, surface a clear error rather than crash on FK.
            throw new \RuntimeException('Cannot create placeholder live class: no lesson exists for this course.');
        }

        return (int) CourseLiveClass::create([
            'batch_id'            => $batch->id,
            'course_id'           => $batch->course_id,
            'lesson_id'           => $lessonId,
            'start_time'          => $date->copy()->setTime(0, 0, 0)->toDateTimeString(),
            'type'                => 'zoom',
            'verification_status' => 'manual',
            'verification_message'=> 'Placeholder for manual attendance entries',
        ])->id;
    }
}
