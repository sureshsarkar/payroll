<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\CustomRecaptcha;
use App\Services\MailSenderService;
use App\Traits\GetGlobalInformationTrait;
use Cache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Str;

class PasswordResetLinkController extends Controller
{
    use GetGlobalInformationTrait;

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
        ? back()->with('status', __($status))
        : back()->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }

    public function custom_forget_password(Request $request)
    {

        $setting = Cache::get('setting');

        $request->validate([
            'email' => ['required', 'email'],
            'g-recaptcha-response' => $setting->recaptcha_status == 'active' ? ['required', new CustomRecaptcha()] : '',
        ], [
            'email.required' => __('Email is required'),
            'g-recaptcha-response.required' => __('Please complete the recaptcha to submit the form'),
        ]);

        // Email-enumeration defense: respond identically whether the
        // address is registered or not. Earlier this returned a generic
        // success only for known addresses and a different "no such
        // account" error for unknowns — letting an attacker iterate
        // addresses to learn which were registered. Same fix as the API audit.
        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->forget_password_token = Str::random(100);
            $user->forget_password_token_expires_at = now()->addHour();
            $user->save();

            (new MailSenderService)->sendUserForgetPasswordFromTrait($user);
        }

        $notification = [
            'messege'    => __('If that email is registered, a password-reset link has been sent.'),
            'alert-type' => 'success',
        ];
        return redirect()->back()->with($notification);
    }
}
