<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile-app companion to Frontend\NotificationController.
 *
 * Mirrors the same per-notification shape() so the web bell-icon and
 * the Android inbox stay aligned. The web controller renders a Blade
 * view for the index page; here we return JSON with cursor-free
 * pagination metadata the mobile app uses for the infinite list.
 *
 * Routes:
 *   GET    /api/notifications              — paginated list (?page=, ?filter=all|unread|read)
 *   GET    /api/notifications/unread-count — { count }
 *   POST   /api/notifications/{id}/read    — mark one read
 *   POST   /api/notifications/read-all     — mark all read
 *   DELETE /api/notifications/{id}         — remove one
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $filter = $request->get('filter', 'all'); // all | unread | read

        $base = $user->notifications();
        if ($filter === 'unread') {
            $base->whereNull('read_at');
        } elseif ($filter === 'read') {
            $base->whereNotNull('read_at');
        }

        $page = $base->paginate(25);

        return response()->json([
            'items'        => collect($page->items())->map(fn ($n) => $this->shape($n))->all(),
            'unread_count' => $user->unreadNotifications()->count(),
            'meta'         => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'has_more'     => $page->hasMorePages(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        // firstOrFail enforces ownership — querying through the user's
        // notifications relation scopes to (notifiable_id, notifiable_type).
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json([
            'status'       => 'success',
            'message'      => 'Marked as read',
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'status'       => 'success',
            'message'      => 'All notifications marked as read',
            'unread_count' => 0,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->delete();

        return response()->json([
            'status'       => 'success',
            'message'      => 'Notification removed',
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Standardized item shape — kept in lock-step with
     * Frontend\NotificationController::shape() so the web dropdown
     * and mobile inbox render the same fields.
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
