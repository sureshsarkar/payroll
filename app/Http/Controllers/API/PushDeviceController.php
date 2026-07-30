<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PushDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Phase 1A of the Student mobile-app build (2026-05-13) — register and
 * unregister push-notification device tokens.
 *
 *   POST   /api/push-tokens          Register (upsert by token)
 *   DELETE /api/push-tokens/{token}  Unregister (called from logout)
 *
 * Both endpoints require auth:sanctum so each row is scoped to a user.
 * The mobile app calls register on every cold-start (token may rotate)
 * and unregister inside its logout flow; the backend uses the unique
 * index on `token` to dedupe.
 */
class PushDeviceController extends Controller
{
    /**
     * Register or refresh a push device token for the authenticated user.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token'       => ['required', 'string', 'max:500'],
            'platform'    => ['required', 'string', 'in:fcm,apns,web'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ], [
            'token.required'    => 'Push token is required',
            'platform.required' => 'Platform is required',
            'platform.in'       => 'Platform must be one of: fcm, apns, web',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'VALIDATION_FAILED',
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user  = $request->user();
        $token = $request->input('token');

        // updateOrCreate keyed on token — handles the case where the same
        // physical device re-registers (post token-rotation) or where a
        // device was previously assigned to a different user account
        // (rare but possible: account switching on a shared phone).
        $device = PushDevice::updateOrCreate(
            ['token' => $token],
            [
                'user_id'      => $user->id,
                'platform'     => $request->input('platform'),
                'device_name'  => $request->input('device_name'),
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Device registered.',
            'data'    => ['id' => $device->id],
        ], 200);
    }

    /**
     * Unregister a push device. Called from the app on logout. Idempotent —
     * returns 200 whether or not the row exists, so re-logout (or a duplicate
     * unregister from a flaky network) doesn't surface an error to the user.
     */
    public function unregister(Request $request, string $token): JsonResponse
    {
        // Scope by user_id so a malicious client can't unregister someone
        // else's device by guessing tokens.
        PushDevice::where('token', $token)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Device unregistered.',
        ], 200);
    }
}
