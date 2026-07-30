<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function custom_reset_password_page(Request $request, $token)
    {

        // Audit fix C2 (2026-05-12) — the URL carries the raw token but the
        // DB stores sha256(raw). Hash before comparing. Also reject expired
        // tokens here so the form never renders for a stale link.
        $hashed = hash('sha256', (string) $token);
        $admin = Admin::select('id', 'name', 'email', 'forget_password_token', 'forget_password_token_expires_at')
            ->where('forget_password_token', $hashed)
            ->first();

        if (! $admin || self::isTokenExpired($admin)) {
            $notification = __('Invalid or expired link. Please request a new one.');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];

            return redirect()->route('admin.password.request')->with($notification);
        }

        return view('admin.auth.reset-password', ['admin' => $admin, 'token' => $token]);
    }

    /**
     * Tokens auto-expire after 1 hour. Rows that pre-date the C2 fix
     * have NULL in this column; we treat NULL as "no TTL" for backward
     * compatibility so existing in-flight tokens still work until they're
     * naturally consumed or the row is updated.
     */
    private static function isTokenExpired(Admin $admin): bool
    {
        if (empty($admin->forget_password_token_expires_at)) {
            return false;
        }
        return now()->greaterThan($admin->forget_password_token_expires_at);
    }

    /**
     * Handle an incoming new password request.
     */
    public function custom_reset_password_store(Request $request, $token)
    {

        $setting = Cache::get('setting');

        // FT-VAL-1 fix (2026-05-27) — added `email` rule so the reset
        // form rejects garbage strings before doing the hashed-token
        // lookup. Tiny defence-in-depth: the token compare is the
        // primary gate, but having a typed email field makes the
        // failure mode predictable and prevents stray Eloquent type
        // coercion on the where('email', $request->email) clause.
        $rules = [
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ];
        $customMessages = [
            'email.required' => __('Email is required'),
            'email.email'    => __('Please enter a valid email address'),
            'password.required' => __('Password is required'),
            'password.min' => __('Password must be 8 characters'),
        ];
        $this->validate($request, $rules, $customMessages);

        // Audit fix C2 (2026-05-12) — hash URL token, compare hashed, reject if expired.
        $hashed = hash('sha256', (string) $token);
        $admin = Admin::select('id', 'name', 'email', 'forget_password_token', 'forget_password_token_expires_at')
            ->where('forget_password_token', $hashed)
            ->where('email', $request->email)
            ->first();

        if (! $admin || self::isTokenExpired($admin)) {
            $notification = __('Invalid or expired link. Please request a new one.');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];

            return redirect()->back()->with($notification);
        }

        $admin->password = Hash::make($request->password);
        $admin->forget_password_token = null;
        $admin->forget_password_token_expires_at = null;
        $admin->save();

        $notification = __('Password Reset successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->route('admin.login')->with($notification);

    }
}
