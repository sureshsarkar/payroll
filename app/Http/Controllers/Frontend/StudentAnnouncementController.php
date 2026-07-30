<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

/**
 * Student-side announcement list + detail + mark-as-read.
 * Phase E of the 2026-05-20 announcement upgrade.
 *
 * Visibility = the three paths defined by
 * Announcement::scopeVisibleToStudent($student):
 *   1. audience_type='all_students'
 *   2. audience_type='batch_specific' AND student is in one of the batches
 *   3. (legacy) course-wide announcement on a course the student is enrolled in
 *
 * Read tracking via the announcement_reads table — markReadBy() on
 * the Announcement model handles idempotency.
 */
class StudentAnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $user = userAuth();
        abort_unless($user, 401);

        // 2026-06-09 — TENANT SCOPE: on a coach domain show only this coach's
        // announcements (sender instructor_id). Null/0 on the platform.
        $tenantCoachId = (int) $request->attributes->get('resolved_coach_id');
        $coachScope = fn ($q) => $q->when($tenantCoachId > 0,
            fn ($x) => $x->where('announcements.instructor_id', $tenantCoachId));

        $base = Announcement::visibleToStudent($user)
            ->tap($coachScope)
            ->with([
                'course:id,title',
                'instructor:id,name',
                'attachments:id,announcement_id,filename,path,size_bytes',
            ]);

        // Optional filter: unread only.
        if ($request->boolean('unread')) {
            $base->whereDoesntHave('readers', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        $announcements = $base
            ->orderByDesc('is_pinned')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Decorate each row with read state for THIS user (single
        // bulk lookup so we don't N+1 inside the view).
        $ids     = $announcements->getCollection()->pluck('id')->all();
        $readMap = empty($ids) ? [] : \DB::table('announcement_reads')
            ->where('user_id', $user->id)
            ->whereIn('announcement_id', $ids)
            ->pluck('read_at', 'announcement_id')
            ->all();

        $announcements->getCollection()->transform(function ($a) use ($readMap) {
            $a->is_read_by_me = isset($readMap[$a->id]);
            $a->read_at       = $readMap[$a->id] ?? null;
            return $a;
        });

        // Headline counts (across full visible set, not just current page).
        $countBase  = Announcement::visibleToStudent($user)->tap($coachScope);
        $totalCount = (clone $countBase)->count();
        $unreadCount = (clone $countBase)
            ->whereDoesntHave('readers', fn ($q) => $q->where('users.id', $user->id))
            ->count();

        return view('frontend.student-dashboard.announcements.index', compact(
            'announcements', 'totalCount', 'unreadCount'
        ));
    }

    /**
     * GET /student/announcements/{id}
     * Detail page — auto-marks as read on open.
     */
    public function show(int $id)
    {
        $user = userAuth();
        abort_unless($user, 401);

        // Visibility gate: only return rows the student is allowed to see.
        // 2026-06-09 — and, on a coach domain, only this coach's announcement.
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        $announcement = Announcement::visibleToStudent($user)
            ->when($tenantCoachId > 0, fn ($q) => $q->where('announcements.instructor_id', $tenantCoachId))
            ->with(['course:id,title', 'instructor:id,name', 'attachments'])
            ->findOrFail($id);

        $announcement->markReadBy($user->id);

        return view('frontend.student-dashboard.announcements.show', compact('announcement'));
    }

    /**
     * POST /student/announcements/{id}/read — AJAX endpoint.
     */
    public function markRead(int $id)
    {
        $user = userAuth();
        abort_unless($user, 401);

        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        $announcement = Announcement::visibleToStudent($user)
            ->when($tenantCoachId > 0, fn ($q) => $q->where('announcements.instructor_id', $tenantCoachId))
            ->findOrFail($id);
        $marked = $announcement->markReadBy($user->id);

        return response()->json([
            'status' => 'ok',
            'newly_marked' => (bool) $marked,
        ]);
    }

    /**
     * GET /student/announcements/unread-count.json — for nav badge.
     */
    public function unreadCount()
    {
        $user = userAuth();
        if (!$user) {
            return response()->json(['unread' => 0]);
        }

        // TENANT SCOPE (2026-06-16 audit M1) — on a coach domain count only this
        // coach's announcements, mirroring index()/show()/markRead(). Without
        // this the nav badge leaked other coaches' unread counts across domains.
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');

        $count = Announcement::visibleToStudent($user)
            ->when($tenantCoachId > 0, fn ($q) => $q->where('announcements.instructor_id', $tenantCoachId))
            ->whereDoesntHave('readers', fn ($q) => $q->where('users.id', $user->id))
            ->count();

        return response()->json(['unread' => $count]);
    }
}
