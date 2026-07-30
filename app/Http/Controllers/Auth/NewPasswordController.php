<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\CustomRecaptcha;
use Cache;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $status == Password::PASSWORD_RESET
        ? redirect()->route('login')->with('status', __($status))
        : back()->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }

    public function custom_reset_password_page(Request $request, $token)
    {

        $user = User::select('id', 'name', 'email', 'forget_password_token')->where('forget_password_token', $token)->first();

        if (! $user) {
            $notification = __('Invalid token, please try again');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];

            return redirect()->route('password.request')->with($notification);
        }

        return view('auth.reset-password', ['user' => $user, 'token' => $token]);
    }

    public function custom_reset_password_store(Request $request, $token)
    {

        $setting = Cache::get('setting');

        // FT-VAL-1 fix (2026-05-27) — added `email` rule so the reset
        // form rejects garbage strings before doing the token lookup.
        // See Admin\Auth\NewPasswordController for the same rationale.
        $rules = [
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
            'g-recaptcha-response' => $setting->recaptcha_status == 'active' ? ['required', new CustomRecaptcha()] : '',
        ];
        $customMessages = [
            'email.required' => __('Email is required'),
            'email.email'    => __('Please enter a valid email address'),
            'password.required' => __('Password is required'),
            'password.min' => __('Password must be at least 8 characters'),
            'g-recaptcha-response.required' => __('Please complete the recaptcha to submit the form'),
        ];
        $this->validate($request, $rules, $customMessages);

        $user = User::select('id', 'name', 'email', 'forget_password_token', 'forget_password_token_expires_at')
            ->where('forget_password_token', $token)
            ->where('email', $request->email)
            ->first();

        if (! $user) {
            $notification = __('Invalid token, please try again');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];

            return redirect()->back()->with($notification);
        }

        // Reject expired tokens (audit added expires_at column + 1h TTL).
        // NULL = legacy reset email from before the migration — accept those
        // so existing flight-or-fight emails don't break, but new ones get
        // the timestamp populated from PasswordResetLinkController.
        if ($user->forget_password_token_expires_at && now()->greaterThan($user->forget_password_token_expires_at)) {
            return redirect()->back()->with([
                'messege'    => __('This password-reset link has expired. Please request a new one.'),
                'alert-type' => 'error',
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->forget_password_token = null;
        $user->forget_password_token_expires_at = null;
        $user->save();

        // Security confirmation (coach-branded for the user's coach).
        try {
            $user->notify(new \App\Notifications\PasswordChangedToUser());
        } catch (\Throwable $e) {
            \Log::warning('Password-changed notify failed: ' . $e->getMessage());
        }

        // Kill all of this user's sessions across every device. A leaked
        // password should not let pre-leak sessions (web or API) continue
        // to operate after the owner takes the password back. The sessions
        // table only exists when SESSION_DRIVER=database (skip otherwise);
        // Sanctum tokens always live in personal_access_tokens.
        if (config('session.driver') === 'database' && \Illuminate\Support\Facades\Schema::hasTable('sessions')) {
            \DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $user->id)
            ->where('tokenable_type', User::class)
            ->delete();

        $notification = __('Password Reset successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->route('login')->with($notification);

    }
}
