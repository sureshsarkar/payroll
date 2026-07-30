<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\MailSenderService;
use App\Traits\GetGlobalInformationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    use GetGlobalInformationTrait;

    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('admin.auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function custom_forget_password(Request $request)
    {

        $setting = Cache::get('setting');

        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => __('Email is required'),
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if ($admin) {
            // Audit fix C2 (2026-05-12) — hash the token before storing.
            // The raw token only ever lives in the email URL; the DB
            // stores sha256(raw). A read-only DB leak no longer hands
            // an attacker a working reset link. We also clamp the token
            // to a 1-hour window so abandoned tokens auto-expire.
            //
            // Why we re-set the in-memory attribute AFTER save():
            // the mail blade template builds the URL from
            // $admin->forget_password_token, so it needs to see the raw
            // value. Reassigning after save() changes only the in-memory
            // attribute — the DB row still holds the hash.
            $rawToken = bin2hex(random_bytes(32));   // 256-bit entropy
            $admin->forget_password_token = hash('sha256', $rawToken);
            $admin->forget_password_token_expires_at = now()->addHour();
            $admin->save();
            $admin->forget_password_token = $rawToken;   // in-memory only — for the mail URL

            (new MailSenderService)->sendUserForgetPasswordFromTrait($admin, 'admin.auth');

            // Email-enumeration defense: respond identically whether the
            // address exists or not.
        }

        // Same response regardless of whether $admin was found — earlier
        // behavior leaked which emails were registered via the 404 /
        // alternate flash message.
        $notification = ['messege' => __('If that email is registered, a password reset link has been sent.'), 'alert-type' => 'success'];
        return redirect()->back()->with($notification);
    }
}
