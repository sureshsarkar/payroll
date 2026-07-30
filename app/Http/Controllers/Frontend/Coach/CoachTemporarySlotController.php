<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachStaff;
use App\Models\CoachStudentLink;
use App\Models\CourseBatch;
use App\Models\StudentTemporarySlot;
use App\Models\TeacherBatchAssignment;
use App\Models\User;
use App\Services\TemporarySlotService;
use Illuminate\Http\Request;
use Modules\Order\app\Models\Enrollment;

/**
 * Coach-panel temporary (date-specific) batch slots (2026-07-15). Assign a
 * student to a different same-course batch for ONE date, keeping their primary
 * batch untouched; list + cancel scheduled slots. Tenant + permission gated,
 * delegates all mutation/validation to TemporarySlotService.
 */
class CoachTemporarySlotController extends Controller
{
    public function __construct(private TemporarySlotService $svc)
    {
    }

    private function coachId(): int
    {
        return userAuth()->role === 'instructor' ? (int) userAuth()->id : (int) userAuth()->coach_id;
    }

    private function ownedStudentOrFail(int $id): User
    {
        $rosterIds = CoachStudentLink::studentIdsForCoach($this->coachId());
        abort_unless(in_array($id, $rosterIds, true), 404);
        return User::where('id', $id)->where('role', 'student')->firstOrFail();
    }

    private function authorise(): void
    {
        $u = userAuth();
        if ($u->role === 'instructor' && empty($u->coach_id)) {
            return;
        }
        $staff = CoachStaff::find($u->id);
        $held  = $staff ? $staff->permissions->pluck('slug')->all() : [];
        abort_unless(in_array('coach-students-temp-slot', $held, true) || in_array('coach-students-edit', $held, true), 403,
            __('You are not allowed to manage temporary slots.'));
    }

    /** Modal data: per enrolled course — primary batch + eligible target batches. */
    public function context(int $id)
    {
        $this->authorise();
        $student = $this->ownedStudentOrFail($id);
        $coachId = $this->coachId();

        $enrollments = Enrollment::where('user_id', $student->id)
            ->whereHas('course', fn ($q) => $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId))
            ->with(['course:id,title', 'batch:id,title'])
            ->get();

        $courses = [];
        foreach ($enrollments->groupBy('course_id') as $courseId => $rows) {
            $courseId = (int) $courseId;
            $primary  = $rows->firstWhere('batch_id', '!==', null);
            $primaryId = $primary ? (int) $primary->batch_id : null;

            $eligible = $this->svc->eligibleBatches($coachId, $courseId, $primaryId)
                ->map(fn ($b) => $this->batchCard($b))->values();

            $courses[] = [
                'course_id'      => $courseId,
                'course_title'   => optional($rows->first()->course)->title ?? ('Course #' . $courseId),
                'primary_batch'  => $primary ? ['id' => $primaryId, 'title' => optional($primary->batch)->title] : null,
                'eligible'       => $eligible,
            ];
        }

        return response()->json([
            'student' => ['id' => $student->id, 'name' => $student->name],
            'courses' => $courses,
            'today'   => now()->toDateString(),
        ]);
    }

    private function batchCard(CourseBatch $b): array
    {
        $days = is_array($b->days) ? implode(', ', array_map(fn ($d) => ucfirst(substr($d, 0, 3)), $b->days)) : '';
        $fmt  = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('g:i A') : '';
        $time = trim($fmt($b->start_time) . ($b->end_time ? ' – ' . $fmt($b->end_time) : ''));
        $teacherId = TeacherBatchAssignment::where('batch_id', $b->id)->where('status', 'active')->value('teacher_id');

        return [
            'id'         => (int) $b->id,
            'title'      => $b->title,
            'schedule'   => trim($days . ($days && $time ? ' · ' : '') . $time),
            'instructor' => $teacherId ? optional(User::find($teacherId))->name : null,
            'capacity'   => $b->hasCapacityLimit() ? (int) $b->capacity : null,
        ];
    }

    /** Assign a temporary slot for a specific date. */
    public function store(Request $request, int $id)
    {
        $this->authorise();
        $student = $this->ownedStudentOrFail($id);
        $coachId = $this->coachId();

        $data = $request->validate([
            'target_batch_id' => 'required|integer',
            'slot_date'       => 'required|date|after_or_equal:today',
            'reason'          => 'nullable|string|max:500',
        ]);

        $batch = CourseBatch::with('course:id,title,instructor_id,added_by')->find($data['target_batch_id']);
        if (! $batch) {
            return $this->respond($request, ['ok' => false, 'message' => __('The selected batch was not found.')], 404);
        }

        $res = $this->svc->assign($coachId, $student->id, $batch, $data['slot_date'], $data['reason'] ?? null, (int) userAuth()->id);
        return $this->respond($request, $res, empty($res['ok']) ? 422 : 200);
    }

    /** Coach-panel list of temporary slots (filters + pagination). */
    public function index(Request $request)
    {
        $this->authorise();
        $coachId = $this->coachId();

        $search = trim((string) $request->get('search'));
        $status = $request->get('status');
        $from   = $request->get('from');
        $to     = $request->get('to');

        $slots = StudentTemporarySlot::forCoach($coachId)
            ->with(['student:id,name,email', 'course:id,title', 'primaryBatch:id,title', 'targetBatch:id,title'])
            ->when($search !== '', fn ($q) => $q->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when(in_array($status, ['scheduled', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->when($from, fn ($q) => $q->whereDate('slot_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('slot_date', '<=', $to))
            ->orderByDesc('slot_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $kpis = [
            'upcoming'  => (int) StudentTemporarySlot::forCoach($coachId)->where('status', 'scheduled')->whereDate('slot_date', '>=', now()->toDateString())->count(),
            'scheduled' => (int) StudentTemporarySlot::forCoach($coachId)->where('status', 'scheduled')->count(),
        ];

        return view('frontend.instructor-dashboard.temporary-slots.index', compact('slots', 'search', 'status', 'from', 'to', 'kpis'));
    }

    /** Cancel a scheduled slot. */
    public function destroy(Request $request, int $slot)
    {
        $this->authorise();
        $res = $this->svc->cancel($this->coachId(), $slot);
        return $this->respond($request, $res, empty($res['ok']) ? 422 : 200);
    }

    private function respond(Request $request, array $payload, int $status = 200)
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($payload, $status);
        }
        return redirect()->back()->with([
            'messege'    => $payload['message'] ?? '',
            'alert-type' => empty($payload['ok']) ? 'error' : 'success',
        ]);
    }
}
