<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachStudentLink;
use App\Models\InstantMeeting;
use App\Models\User;
use App\Services\InstantMeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Coach-panel 1:1 Instant Meeting: pick one of YOUR students and start a private
 * Zoom room now. Everything scoped to the coach (instructor = self, staff =
 * their coach_id). Separate from batch/group Live Classes.
 */
class InstantMeetingController extends Controller
{
    public function __construct(private InstantMeetingService $service) {}

    private function coachId(): int
    {
        return userAuth()->role === 'instructor' ? (int) userAuth()->id : (int) userAuth()->coach_id;
    }

    public function index(Request $request)
    {
        $coachId = $this->coachId();

        $studentIds = CoachStudentLink::studentIdsForCoach($coachId);
        $students = User::whereIn('id', $studentIds ?: [0])
            ->where('role', 'student')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $recent = InstantMeeting::forCoach($coachId)
            ->with('student:id,name,email')
            ->latest()
            ->limit(15)
            ->get();

        // A coach can only run one meeting at a time — surface the active one.
        $active = InstantMeeting::forCoach($coachId)
            ->where('status', InstantMeeting::STATUS_ACTIVE)
            ->latest()
            ->first();

        $zoomConfigured = (bool) optional(userAuth()->coach_id ? User::find($coachId) : userAuth())->zoom_credential?->sdk_key;

        return view('frontend.instructor-dashboard.instant-meetings.index', compact('students', 'recent', 'active', 'zoomConfigured'));
    }

    public function start(Request $request): JsonResponse
    {
        $coachId = $this->coachId();

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'purpose'    => ['nullable', Rule::in(['consultation', 'doubt', 'training', 'general'])],
            'topic'      => ['nullable', 'string', 'max:190'],
            'duration'   => ['nullable', 'integer', 'min:5', 'max:240'],
        ]);

        // The student MUST belong to this coach (tenant isolation).
        $studentIds = CoachStudentLink::studentIdsForCoach($coachId);
        if (! in_array((int) $data['student_id'], array_map('intval', $studentIds), true)) {
            return response()->json(['ok' => false, 'message' => __('That student is not in your roster.')], 422);
        }

        $result = $this->service->start($coachId, (int) $data['student_id'], [
            'purpose'  => $data['purpose'] ?? 'general',
            'topic'    => $data['topic'] ?? null,
            'duration' => $data['duration'] ?? 30,
            'ip'       => $request->ip(),
        ]);

        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? __('Could not start the meeting.')], $result['code'] ?? 422);
        }

        $meeting = $result['meeting'];

        return response()->json([
            'ok'           => true,
            'message'      => __('Meeting started. Your student has been notified.'),
            'meeting_id'   => $meeting->id,
            'redirect_url' => route('instant-meeting.room', $meeting->id),
        ], 200);
    }

    public function end(Request $request, $id): JsonResponse
    {
        $coachId = $this->coachId();
        $meeting = InstantMeeting::forCoach($coachId)->findOrFail($id);

        $this->service->end($meeting);

        return response()->json(['ok' => true, 'message' => __('Meeting ended.')], 200);
    }
}
