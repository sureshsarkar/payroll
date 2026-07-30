<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AnnouncementAttachment;
use App\Models\Course;
use App\Models\CourseLiveClass;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Endpoints the iOS app needs that don't fit neatly into the existing
 * Student / Coach controllers. Keeping them in one file makes the
 * iOS↔Laravel surface easy to audit.
 *
 *   1. GET    /api/certificates
 *        — list completed-course "certificates" for the signed-in user.
 *   2. GET    /api/instructor/courses/{courseId}/attendance
 *        — aggregate attendance across every live class on a course.
 *   3. POST   /api/instructor/courses/{courseId}/thumbnail
 *        — multipart course thumbnail upload.
 *   4. GET/POST /api/messages, /api/messages/{peerId}
 *        — role-agnostic DM endpoints so students can also message
 *          their instructors (the existing /api/instructor/messages
 *          group only lets instructors initiate).
 *
 * Announcement attachments — also requested by the iOS app — requires
 * a new `announcement_attachments` table that doesn't exist yet, so
 * it stays out of this batch. iOS already handles a 404 gracefully.
 */
class MobileExtrasController extends Controller
{
    /* ─── 1. Certificates ──────────────────────────────────────────── */

    /**
     * Mobile-friendly certificates list. The backend doesn't have a
     * dedicated `certificates` table — a "certificate" is a course
     * the user has completed. The actual PDF is generated on demand
     * by /api/download-certificate/{slug}.
     */
    public function certificates(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $rows = Enrollment::query()
            ->with(['course:id,slug,title,thumbnail'])
            ->where('user_id', $userId)
            ->where(function ($q) {
                $q->where('progress', '>=', 100)
                  ->orWhere('is_completed', 1);
            })
            ->orderByDesc('updated_at')
            ->get();

        $data = $rows->map(function ($e) {
            return [
                'id'           => $e->id,
                'course_id'    => $e->course_id,
                'course_slug'  => optional($e->course)->slug,
                'course_title' => optional($e->course)->title ?? 'Course',
                'issued_at'    => optional($e->updated_at)->toIso8601String(),
                'thumbnail'    => optional($e->course)->thumbnail,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /* ─── 2. Per-course attendance (aggregate) ─────────────────────── */

    /**
     * Aggregate attendance for one course across every live class
     * scheduled under it. Returns per-student rows the iOS
     * AttendanceView already expects.
     */
    public function courseAttendance(Request $request, int $courseId): JsonResponse
    {
        $instructor = $request->user();

        // Auth gate — instructors can only see their own courses.
        $course = Course::where('id', $courseId)
            ->where('user_id', $instructor->id)
            ->first();
        if (!$course) {
            return response()->json(['status' => 'error', 'message' => 'Course not found'], 404);
        }

        // Total live-class sessions on this course.
        $totalSessions = CourseLiveClass::where('course_id', $courseId)->count();

        // Per-student tallies: how many of those sessions they attended.
        $rows = DB::table('live_class_attendances as a')
            ->join('live_classes as lc', 'lc.id', '=', 'a.live_class_id')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->where('lc.course_id', $courseId)
            ->select(
                'u.id', 'u.name', 'u.email',
                DB::raw('COUNT(DISTINCT a.live_class_id) as attended_count'),
                DB::raw('MAX(a.created_at) as last_attended_at')
            )
            ->groupBy('u.id', 'u.name', 'u.email')
            ->orderByDesc('attended_count')
            ->get();

        $students = $rows->map(function ($r) use ($totalSessions) {
            $pct = $totalSessions > 0
                ? round(($r->attended_count / $totalSessions) * 100, 1)
                : 0;
            return [
                'id'                => $r->id,
                'name'              => $r->name,
                'email'             => $r->email,
                'attended_count'    => (int) $r->attended_count,
                'total_sessions'    => $totalSessions,
                'percent'           => $pct,
                'last_attended_at'  => $r->last_attended_at
                    ? \Carbon\Carbon::parse($r->last_attended_at)->toIso8601String()
                    : null,
            ];
        });

        return response()->json([
            'status'         => 'success',
            'course_id'      => $courseId,
            'total_sessions' => $totalSessions,
            'students'       => $students,
        ]);
    }

    /* ─── 3. Course thumbnail upload ───────────────────────────────── */

    /**
     * Upload (or replace) the thumbnail for a course the signed-in
     * instructor owns.
     */
    public function uploadCourseThumbnail(Request $request, int $courseId): JsonResponse
    {
        $instructor = $request->user();

        $validator = Validator::make($request->all(), [
            'thumbnail' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $course = Course::where('id', $courseId)
            ->where('user_id', $instructor->id)
            ->first();
        if (!$course) {
            return response()->json(['status' => 'error', 'message' => 'Course not found'], 404);
        }

        $file = $request->file('thumbnail');
        $filename = 'course-' . $courseId . '-' . Str::random(8) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('uploads/course-thumbnails', $filename, 'public');

        $course->thumbnail = $path;
        $course->save();

        return response()->json([
            'status'    => 'success',
            'message'   => 'Thumbnail updated.',
            'thumbnail' => $path,
        ]);
    }

    /* ─── 4. Announcement attachments ──────────────────────────────── */

    /**
     * Append a file to an existing announcement. iOS calls this in a
     * loop after creating the announcement (one POST per picked image).
     * Backed by the announcement_attachments table created in the
     * 2026-05-22 migration.
     */
    public function uploadAnnouncementAttachment(Request $request, int $announcementId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:10240'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Only the announcement's author can attach files.
        $announcement = DB::table('announcements')->where('id', $announcementId)->first();
        if (!$announcement || (int) $announcement->user_id !== (int) $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Announcement not found'], 404);
        }

        $file = $request->file('file');
        $filename = 'ann-' . $announcementId . '-' . Str::random(8) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('uploads/announcement-attachments', $filename, 'public');

        $attachment = AnnouncementAttachment::create([
            'announcement_id' => $announcementId,
            'path'            => $path,
            'original_name'   => $file->getClientOriginalName(),
            'size_bytes'      => $file->getSize(),
            'mime_type'       => $file->getMimeType(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Attachment added.',
            'data'    => [
                'id'            => $attachment->id,
                'url'           => Storage::url($path),
                'original_name' => $attachment->original_name,
                'size_bytes'    => $attachment->size_bytes,
            ],
        ]);
    }

    /* ─── 5. Generic messages (student + instructor) ───────────────── */

    /**
     * Conversation list — every user the signed-in user has exchanged
     * a DM with. Works for students AND instructors. Uses the
     * low_user_id/high_user_id pair index for the latest-per-peer
     * lookup.
     */
    public function messagesIndex(Request $request): JsonResponse
    {
        $me = $request->user()->id;

        // Latest message id per pair.
        $latest = DB::table('direct_messages')
            ->select('low_user_id', 'high_user_id', DB::raw('MAX(id) as last_id'))
            ->where(function ($q) use ($me) {
                $q->where('sender_id', $me)->orWhere('recipient_id', $me);
            })
            ->groupBy('low_user_id', 'high_user_id');

        $rows = DB::table('direct_messages as m')
            ->joinSub($latest, 'l', 'l.last_id', '=', 'm.id')
            ->orderByDesc('m.created_at')
            ->get();

        $peerIds = $rows->map(fn ($r) => $r->sender_id === $me ? $r->recipient_id : $r->sender_id)
            ->unique()->values();

        $users = User::whereIn('id', $peerIds)
            ->select('id', 'name', 'email', 'image')
            ->get()
            ->keyBy('id');

        $unread = DB::table('direct_messages')
            ->where('recipient_id', $me)
            ->whereNull('read_at')
            ->select('sender_id', DB::raw('COUNT(*) as c'))
            ->groupBy('sender_id')
            ->pluck('c', 'sender_id');

        $data = $rows->map(function ($r) use ($me, $users, $unread) {
            $peerId = $r->sender_id === $me ? $r->recipient_id : $r->sender_id;
            $peer = $users->get($peerId);
            return [
                'id'                => $peerId,
                'other_user_id'     => $peerId,
                'other_user_name'   => optional($peer)->name,
                'other_user_image'  => optional($peer)->image,
                'last_message'      => $r->body,
                'last_message_at'   => \Carbon\Carbon::parse($r->created_at)->toIso8601String(),
                'unread_count'      => (int) ($unread[$peerId] ?? 0),
            ];
        });

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Thread with one peer. Most-recent-first paging via ?before=N.
     */
    public function messagesThread(Request $request, int $peerId): JsonResponse
    {
        $me = $request->user()->id;
        if ($peerId === $me) {
            return response()->json(['status' => 'error', 'message' => 'Invalid peer'], 422);
        }

        $limit = min(100, max(10, (int) $request->query('limit', 50)));
        $before = (int) $request->query('before', 0);

        $low  = min($me, $peerId);
        $high = max($me, $peerId);

        $q = DB::table('direct_messages')
            ->where('low_user_id', $low)
            ->where('high_user_id', $high)
            ->orderByDesc('id');

        if ($before > 0) {
            $q->where('id', '<', $before);
        }

        $messages = $q->limit($limit)->get()->reverse()->values();

        // Mark every inbound msg as read.
        DB::table('direct_messages')
            ->where('sender_id', $peerId)
            ->where('recipient_id', $me)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $data = $messages->map(function ($m) use ($me) {
            return [
                'id'      => $m->id,
                'body'    => $m->body,
                'sent_at' => \Carbon\Carbon::parse($m->created_at)->toIso8601String(),
                'is_mine' => $m->sender_id === $me,
            ];
        });

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Send a message to a peer. Any authenticated user can DM any
     * other authenticated user. Tighter checks (must be your
     * instructor or your student) can be added later once the demo
     * flow is working end to end.
     */
    public function messagesSend(Request $request, int $peerId): JsonResponse
    {
        $me = $request->user()->id;

        $validator = Validator::make($request->all(), [
            'body' => ['required', 'string', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }
        if ($peerId === $me) {
            return response()->json(['status' => 'error', 'message' => 'Invalid recipient'], 422);
        }

        $peer = User::find($peerId);
        if (!$peer) {
            return response()->json(['status' => 'error', 'message' => 'Recipient not found'], 404);
        }

        $low  = min($me, $peerId);
        $high = max($me, $peerId);

        $id = DB::table('direct_messages')->insertGetId([
            'sender_id'     => $me,
            'recipient_id'  => $peerId,
            'low_user_id'   => $low,
            'high_user_id'  => $high,
            'body'          => $request->input('body'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'      => $id,
                'body'    => $request->input('body'),
                'sent_at' => now()->toIso8601String(),
                'is_mine' => true,
            ],
        ]);
    }

    /**
     * Mark every message from this peer as read. Idempotent.
     */
    public function messagesMarkRead(Request $request, int $peerId): JsonResponse
    {
        $me = $request->user()->id;
        DB::table('direct_messages')
            ->where('sender_id', $peerId)
            ->where('recipient_id', $me)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return response()->json(['status' => 'success']);
    }
}
