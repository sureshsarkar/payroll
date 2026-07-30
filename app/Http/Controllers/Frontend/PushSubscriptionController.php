<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saves browser-issued PushSubscription objects to the user's account so
 * the WebPushChannel can reach them next time we dispatch a notification.
 *
 * POST /push/subscribe       — subscribe (idempotent — re-subscribing replaces)
 * DELETE /push/subscribe     — unsubscribe (cleanup if user disables)
 *
 * Payload shape (sent by browser via PushSubscription.toJSON()):
 *   {
 *     "endpoint": "https://fcm.googleapis.com/...",
 *     "keys": { "p256dh": "...", "auth": "..." }
 *   }
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint'    => ['required', 'url', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth'   => ['required', 'string', 'max:200'],
        ]);

        $request->user()->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth'),
            'aesgcm'
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $endpoint = $request->input('endpoint');
        if ($endpoint) {
            $request->user()->deletePushSubscription($endpoint);
        }
        return response()->json(['ok' => true]);
    }
}
