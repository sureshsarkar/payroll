<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Phase 1B of the Student mobile-app build (2026-05-13) — user-side 2FA
 * over the API. Mirrors the admin-side flow in
 * `App\Http\Controllers\Admin\TwoFactorController` but consumed by the
 * mobile app rather than a Blade view.
 *
 * Endpoints (all under `routes/api.php`):
 *
 *   POST /api/2fa/enable               sanctum    Begin enrollment — returns QR + secret + recovery
 *   POST /api/2fa/confirm              sanctum    Verify first TOTP code; mark confirmed
 *   POST /api/2fa/disable              sanctum    Disable with password re-entry
 *   POST /api/2fa/regenerate-recovery  sanctum    Fresh recovery codes
 *   POST /api/2fa/verify               guest      Verify TOTP during login challenge (with challenge token)
 *
 * Login challenge:
 *   - When a user with 2FA enabled hits `POST /api/login`, the controller
 *     mints a short-lived challenge_token (cached for 15 min, single-use)
 *     and returns it INSTEAD of the bearer token.
 *   - The mobile app posts `{ challenge_token, code }` to /api/2fa/verify.
 *   - On success this endpoint mints the actual Sanctum bearer + role.
 */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorAuthService $tfa) {}

    /* ============================================================ Setup =====================*/

    /**
     * Begin enrollment. Idempotent if there's an already-pending
     * (unconfirmed) enrollment — re-running issues a fresh secret +
     * codes and forgets the prior pending row. Active confirmed 2FA
     * must be disabled first.
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'ALREADY_ENABLED',
                'message' => '2FA is already active. Disable it first to re-enrol.',
            ], 409);
        }

        $secret         = $this->tfa->generateSecret();
        $recoveryCodes  = $this->tfa->generateRecoveryCodes();

        $user->two_factor_secret         = $secret;
        $user->two_factor_recovery_codes = $recoveryCodes;
        $user->two_factor_enabled_at     = now();
        $user->two_factor_confirmed_at   = null;
        $user->save();

        $otpAuth = $this->tfa->otpauthUrl(
            $user->email,
            config('app.name', 'MBSGuru'),
            $secret
        );
        $qrSvg = $this->tfa->qrSvg($otpAuth, 220);

        return response()->json([
            'status'  => 'success',
            'message' => 'Scan the QR with your authenticator app, then call /api/2fa/confirm with a 6-digit code.',
            'data'    => [
                'secret'         => $secret,
                'otpauth_url'    => $otpAuth,
                'qr_svg'         => $qrSvg,
                'recovery_codes' => $recoveryCodes,
            ],
        ], 200);
    }

    /**
     * Confirm enrollment with the first 6-digit code from the user's
     * authenticator app.
     */
    public function confirm(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'size:6'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'VALIDATION_FAILED',
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        if (!$user->two_factor_secret) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'NO_PENDING_ENROLLMENT',
                'message' => 'No pending 2FA enrolment. Call /api/2fa/enable first.',
            ], 409);
        }

        if (!$this->tfa->verifyCode($user->two_factor_secret, $request->input('code'))) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'INVALID_CODE',
                'message' => 'Invalid code. Make sure your phone clock is in sync.',
            ], 422);
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Two-factor authentication is now active.',
        ], 200);
    }

    /**
     * Disable 2FA. Requires password re-entry so a hijacked Sanctum
     * token can't silently strip the second factor from an account.
     */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'VALIDATION_FAILED',
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        if (!Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'INVALID_PASSWORD',
                'message' => 'Incorrect password.',
            ], 401);
        }

        $user->two_factor_secret         = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_enabled_at     = null;
        $user->two_factor_confirmed_at   = null;
        $user->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Two-factor authentication has been disabled.',
        ], 200);
    }

    /**
     * Regenerate the 8 single-use recovery codes. Replaces all prior
     * codes — once issued, the old ones stop working immediately.
     */
    public function regenerateRecovery(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'NOT_ENABLED',
                'message' => '2FA is not active on this account.',
            ], 403);
        }

        $user->two_factor_recovery_codes = $this->tfa->generateRecoveryCodes();
        $user->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Fresh recovery codes generated. The old codes no longer work.',
            'data'    => [
                'recovery_codes' => $user->two_factor_recovery_codes,
            ],
        ], 200);
    }

    /* ============================================================ Challenge =================*/

    /**
     * Cache-key prefix for the per-challenge mapping {challenge_token → user_id}.
     */
    public const CHALLENGE_CACHE_PREFIX = 'api:2fa:challenge:';

    /**
     * Cache TTL for a challenge token. 15 minutes is generous enough for
     * a user fumbling through their authenticator app on a different
     * device + a 1-minute clock-skew tolerance, while keeping the
     * window short enough that a leaked challenge is not durable.
     */
    public const CHALLENGE_TTL_SECONDS = 900;

    /**
     * Mint a new challenge token for a successful password-verified user.
     * Called from `Api\AuthenticatedController::login` when the user has
     * 2FA enabled. Kept here so the cache key + TTL stay in one file.
     */
    public static function issueChallenge(User $user): string
    {
        $token = bin2hex(random_bytes(32));  // 256-bit, hex
        Cache::put(
            self::CHALLENGE_CACHE_PREFIX . hash('sha256', $token),
            $user->id,
            self::CHALLENGE_TTL_SECONDS
        );
        return $token;
    }

    /**
     * Verify TOTP (or recovery) code against a previously-issued challenge.
     * On success returns the actual Sanctum bearer the app should store.
     *
     * Single-use: the challenge cache entry is deleted on success so the
     * same challenge_token can't be replayed by a network intercept.
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => ['required', 'string'],
            'code'            => ['required', 'string', 'min:6', 'max:32'],   // 6 = TOTP, longer = recovery
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'VALIDATION_FAILED',
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $hashed  = hash('sha256', $request->input('challenge_token'));
        $cacheKey = self::CHALLENGE_CACHE_PREFIX . $hashed;
        $userId  = Cache::get($cacheKey);

        if (!$userId) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'CHALLENGE_EXPIRED',
                'message' => 'Challenge expired or invalid. Sign in again.',
            ], 403);
        }

        $user = User::find($userId);
        if (!$user || !$user->hasTwoFactorEnabled()) {
            Cache::forget($cacheKey);
            return response()->json([
                'status'  => 'error',
                'code'    => 'CHALLENGE_INVALID',
                'message' => 'Challenge no longer valid.',
            ], 403);
        }

        $code = (string) $request->input('code');

        // Try TOTP first (6-digit numeric). If that fails AND the code
        // looks like a recovery code (10–11 chars, contains hyphen), try
        // recovery. This keeps the API simple — caller sends one `code`
        // field regardless.
        $accepted = false;
        if (strlen($code) === 6 && ctype_digit($code)) {
            $accepted = $this->tfa->verifyCode($user->two_factor_secret, $code);
        }
        if (!$accepted && str_contains($code, '-')) {
            $accepted = $this->tfa->consumeRecoveryCode($user, $code);
            if ($accepted) {
                $user->save();   // persist consumed-codes array
            }
        }

        if (!$accepted) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'INVALID_CODE',
                'message' => 'Invalid code.',
            ], 401);
        }

        // Single-use challenge: invalidate now.
        Cache::forget($cacheKey);

        // Mint the bearer the same way Api\AuthenticatedController::login
        // does — same abilities scope, same token name.
        $abilities    = ['role:' . $user->role];
        $bearer_token = $user->createToken($user->role, $abilities)->plainTextToken;

        return response()->json([
            'status'       => 'success',
            'message'      => 'Logged in successfully.',
            'bearer_token' => $bearer_token,
            'user_id'      => $user->id,
            'role'         => $user->role,
        ], 200);
    }
}
