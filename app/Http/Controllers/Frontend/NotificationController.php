<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints for the bell-icon notification UI used by both coach and student dashboards.
 *
 * GET    /notifications              — paginated list of all notifications
 * GET    /notifications/unread-count — { count: N } for the bell badge
 * GET    /notifications/recent       — last 10 (used by dropdown)
 * POST   /notifications/{id}/read    — mark one read
 * POST   /notifications/read-all     — mark all read
 * DELETE /notifications/{id}         — remove one
 */
class NotificationController extends Controller
{
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = $user->notifications()->limit(10)->get()->map(fn ($n) => $this->shape($n));
        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'items'        => $items,
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filter = $request->get('filter', 'all'); // all | unread | read
        $q = trim((string) $request->get('q', ''));

        $base = $user->notifications();

        if ($filter === 'unread') {
            $base->whereNull('read_at');
        } elseif ($filter === 'read') {
            $base->whereNotNull('read_at');
        }

        if ($q !== '') {
            // SQLite/MySQL JSON LIKE — works on the cast `data` text column.
            $needle = '%' . $q . '%';
            $base->where('data', 'like', $needle);
        }

        $notifications = $base->paginate(25)->withQueryString();

        // Headline stats (independent of current filter). F47 (audit 2026-06-26)
        // — collapse 5 separate COUNT queries into ONE conditional-aggregate pass
        // over the user's own notifications.
        $stats = $user->notifications()->selectRaw(
            'COUNT(*) as all_count, '
            . 'SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread_count, '
            . 'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as today_count, '
            . 'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as week_count',
            [today()->startOfDay(), now()->subDays(7)]
        )->first();
        $allCount    = (int) ($stats->all_count ?? 0);
        $unreadCount = (int) ($stats->unread_count ?? 0);
        $readCount   = max($allCount - $unreadCount, 0);
        $todayCount  = (int) ($stats->today_count ?? 0);
        $weekCount   = (int) ($stats->week_count ?? 0);

        $view = $request->is('admin/*') ? 'admin.notifications.index' : 'frontend.notifications.index';
        return view($view, compact(
            'notifications', 'filter', 'q',
            'allCount', 'unreadCount', 'readCount', 'todayCount', 'weekCount'
        ));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();
        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Standardized response shape for the dropdown UI.
     */
    private function shape($n): array
    {
        return [
            'id'         => $n->id,
            'title'      => $n->data['title']     ?? '',
            'body'       => $n->data['body']      ?? '',
            'url'        => $n->data['url']       ?? null,
            'icon'       => $n->data['icon']      ?? 'fa-bell',
            'iconColor'  => $n->data['iconColor'] ?? '#5751e1',
            'read'       => $n->read_at !== null,
            'created_at' => $n->created_at?->toIso8601String(),
            'time_ago'   => $n->created_at?->diffForHumans(),
        ];
    }
}
