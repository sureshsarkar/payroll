<?php

namespace App\Http\Controllers\API;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MailSenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\GlobalSetting\app\Models\MarketingSetting;
use Modules\GlobalSetting\app\Models\Setting;

class AuthenticatedController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            // 'role' is user-supplied — restrict to the public-registerable
            // roles. Without this, anyone could POST role=admin and self-elevate.
            'role' => ['required', 'string', 'in:student,instructor'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', 'min:8', 'max:100'],
        ], [
            'name.required' => 'Name is required',
            'email.required' => 'Email is required',
            'email.unique' => 'Email already exist',
            'password.required' => 'Password is required',
            'password.confirmed' => 'Confirm password does not match',
            'password.min' => 'Password must be at least 8 characters',
            'role.in'        => 'Invalid role',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role,
                'status' => 'active',
                'is_banned' => 'no',
                'password' => Hash::make($request->password),
                'verification_token' => Str::random(100),
            ]);

            if (!(new MailSenderService)->sendVerifyMailToUserFromTrait('single_user', $user)) {
                throw new \Exception('Failed to send email.');
            }
            DB::commit();

            $google_tagmanager_status = Setting::where('key', 'google_tagmanager_status')->value('value');
            $marketing_setting_register = MarketingSetting::where('key', 'register')->value('value');
            if ($user && $google_tagmanager_status == 'active' && $marketing_setting_register) {
                $register_user = [
                    'name' => $user->name,
                    'email' => $user->email,
                ];
                session()->put('registerUser', $register_user);
            }

             

            return response()->json([
                'status' => 'success',
                'message' => 'A varification link has been send to your mail, please verify and enjoy our service',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed due to an issue with sending the verification email. Please try again later.',
            ], 500);

        }
    }

    public function forgetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Email is required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        // Email-enumeration defense: always respond identically whether the
        // address is registered or not. Earlier this returned 404 + "Email
        // does not exist" for unknowns and 200 + "link sent" for hits, which
        // let an attacker iterate addresses to learn which were registered.
        $user = User::where('email', $request->email)->first();
        if ($user) {
            // Audit fix C2-user-side (2026-05-12) — mirror of the admin-side
            // fix. Raw token only ever lives in the email URL; DB stores
            // sha256(raw). A read-only DB leak no longer hands an attacker
            // working reset links. Email-rendering reads $user->forget_password_token,
            // so we re-assign the raw value in-memory AFTER save() — that
            // doesn't persist but lets the mailable build the URL.
            $rawToken = bin2hex(random_bytes(32));   // 256-bit entropy
            $user->forget_password_token = hash('sha256', $rawToken);
            $user->forget_password_token_expires_at = now()->addHour();
            $user->save();
            $user->forget_password_token = $rawToken;   // in-memory only — for the mail URL
            (new MailSenderService)->sendUserForgetPasswordFromTrait($user);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'If that email is registered, a password-reset link has been sent.',
        ], 200);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'forget_password_token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', 'min:8', 'max:100'],
        ], [
            'email.required' => 'Email is required',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'forget_password_token.required' => 'Forget password token is required',
            'password.confirmed' => 'Confirm password does not match',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        // Audit fix C2-user-side (2026-05-12) — the URL/API request carries the
        // raw token but the DB stores sha256(raw). Hash before comparing.
        $hashedToken = hash('sha256', (string) $request->forget_password_token);
        $user = User::select('id', 'name', 'email', 'forget_password_token', 'forget_password_token_expires_at')
            ->where('forget_password_token', $hashedToken)
            ->where('email', $request->email)
            ->first();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid token, please try again',
            ], 400);
        }

        // Reject expired tokens. NULL = legacy row from before the migration —
        // treat as still valid so existing reset emails don't break, but issue
        // new ones with the timestamp populated.
        if ($user->forget_password_token_expires_at && now()->greaterThan($user->forget_password_token_expires_at)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This password-reset link has expired. Please request a new one.',
            ], 400);
        }

        // Update the user's password and invalidate ALL existing sessions:
        // a leaked password should not give anyone with old tokens continued
        // access after the password owner takes it back.
        $user->password = Hash::make($request->password);
        $user->forget_password_token = null;
        $user->forget_password_token_expires_at = null;
        $user->save();
        PersonalAccessToken::where('tokenable_id', $user->id)
            ->where('tokenable_type', User::class)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Password Reset successfully',
        ], 200);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Email is required',
            'password.required' => 'Password is required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // Check if user exists and password match
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid credentials please check your email and password'], 401);
        }
        // Check if user active
        if ($user->status != UserStatus::ACTIVE->value) {
            return response()->json(['status' => 'error', 'message' => 'Inactive account'], 403);
        }
        // Check if user is banned
        if ($user->is_banned == UserStatus::BANNED->value) {
            return response()->json(['status' => 'error', 'message' => 'Your account has been banned'], 403);
        }

        // Check if email is verified
        if (! $user->email_verified_at) {
            return response()->json(['status' => 'error', 'message' => 'Please verify your email'], 403);
        }

        // TENANT ISOLATION (2026-06-16) — if this API request is served on a
        // coach white-label host (resolved from coach_domains), the user must
        // belong to that coach. On the platform/api host the surface id is 0 and
        // this is a no-op, so the existing single-catalog mobile app is
        // unaffected. See \App\Support\TenantAccess.
        $apiCoachId = \App\Support\TenantAccess::apiSurfaceCoachId($request);
        if ($apiCoachId > 0 && ! \App\Support\TenantAccess::userMayAccessCoach($user, $apiCoachId)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'These credentials are not authorized for this website.',
            ], 403);
        }

        // Phase 1C of the Student mobile-app build (2026-05-13) — if the
        // user has confirmed 2FA, do NOT issue the bearer token yet.
        // Mint a short-lived challenge token (15 min, single-use, stored
        // in cache by sha256 hash) and require the mobile app to POST
        // /api/2fa/verify with the user's TOTP or recovery code to
        // exchange it for the actual Sanctum bearer. See
        // App\Http\Controllers\API\TwoFactorController for the verify flow.
        if ($user->hasTwoFactorEnabled()) {
            $challengeToken = \App\Http\Controllers\API\TwoFactorController::issueChallenge($user);
            return response()->json([
                'status'          => 'success',
                'message'         => 'Two-factor verification required.',
                'requires_2fa'    => true,
                'challenge_token' => $challengeToken,
                'user_id'         => $user->id,
            ], 200);
        }

        // delete all extra token
        PersonalAccessToken::where('tokenable_id', $user->id)->where('tokenable_type', 'App\Models\User')->where('name', 'extra-token')->delete();

        // Token is named for the actual user role and scoped to that role's
        // abilities — auth:sanctum guards still allow this token through, but
        // any future ability checks (Auth::user()->tokenCan('admin:write'))
        // can rely on the scope instead of trusting a wildcard. Tokens
        // expire after the value in config/sanctum.php (defaults to 7 days
        // post-audit). null = no expiration, which lets a stolen token live
        // forever.
        $abilities    = ['role:' . $user->role];
        $bearer_token = $user->createToken($user->role, $abilities)->plainTextToken;

        return response()->json([
            'status'       => 'success',
            'message'      => 'Logged in successfully.',
            'requires_2fa' => false,
            'bearer_token' => $bearer_token,
            'user_id'      => $user->id,
            'role'         => $user->role,
        ], 200);
    }

    public function logout(): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // delete all extra token
        PersonalAccessToken::where('tokenable_id', $user->id)->where('tokenable_type', 'App\Models\User')->where('name', 'extra-token')->delete();
        $user->currentAccessToken()->delete();

        return response()->json(['status' => 'success', 'message' => 'Logged out successfully.'], 200);
    }

    public function logoutAllApp(): JsonResponse
    {
        if (!auth()->check()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated.'
                ], 401);
            }

        auth()->user()->tokens()->delete();

        return response()->json(['status' => 'success', 'message' => 'Logged out successfully.'], 200);
    }
}
