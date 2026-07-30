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
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Str;

class RegisteredUserController extends Controller
{
    use GetGlobalInformationTrait;

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        
        $setting = Cache::get('setting');
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            // FT-AUTH-1 fix (2026-05-27) — added `in:` whitelist.
            // Before: `role: required|string|max:20` accepted ANY string.
            // Combined with mass-assignment via User::create([…'role'=>$request->role]),
            // an attacker could POST role=admin and self-elevate. The API
            // controller (API/AuthenticatedController.php:27) already had
            // the in:student,instructor whitelist; the web path missed it.
            'role' => ['required', 'string', 'in:student,instructor'],
            'password' => ['required', 'confirmed', 'min:8', 'max:100'],
            'referral_code' => ['nullable', 'string', 'max:20'],
            'g-recaptcha-response' => $setting->recaptcha_status == 'active' ? ['required', new CustomRecaptcha()] : '',
        ], [
            'name.required' => __('Name is required'),
            'email.required' => __('Email is required'),
            'email.unique' => __('Email already exist'),
            'password.required' => __('Password is required'),
            'password.confirmed' => __('Confirm password does not match'),
            'password.min' => __('You have to provide minimum 8 character password'),
            'g-recaptcha-response.required' => __('Please complete the recaptcha to submit the form'),
        ]);

        $username = Str::slug($request->name);
        $lastId = User::latest()->value('id');

        $coach_unique_id = ($request->role=='instructor')?"MBS".$lastId:"MBS".$lastId;
        // Referral attribution. Order of preference:
        //   1. explicit `referral_code` field on the form (most authoritative — user typed it)
        //   2. `mbs_ref` cookie dropped by TrackReferralCookie middleware on ?ref=CODE visit
        $refCode = trim((string) ($request->input('referral_code') ?: $request->cookie('mbs_ref')));
        $refCode = $refCode !== '' ? strtoupper($refCode) : null;
        $referrer = $refCode ? User::where('referral_code', $refCode)->first() : null;

        $user = User::create([
            'coach_unique_id' => $coach_unique_id,
            'role' => $request->role,
            'name' => $request->name,
            'username' => $username,
            'email' => $request->email,
            'status' => 'active',
            'is_banned' => 'no',
            'password' => Hash::make($request->password),
            'verification_token' => Str::random(100),
            'referred_by_user_id' => $referrer?->id,
            'referred_at' => $referrer ? now() : null,
        ]);

        // TENANT ISOLATION (2026-06-16) — if registration happens on a coach
        // white-label surface, attribute the new student to that coach so they
        // become (and can log back in as) that coach's student. No-op on the
        // platform host. The coach-site register flow already links explicitly;
        // this covers the platform form when served on a resolved coach domain.
        \App\Support\TenantAccess::autoLinkIfStudent(
            $user,
            \App\Support\TenantAccess::surfaceCoachId($request),
            'invite'
        );

        // Create a referral lifecycle row (separate from the legacy
        // referred_by_user_id snapshot) and run fraud guards. Reward stays
        // pending until the user activates their first paid membership.
        try {
            app(\App\Services\ReferralRewardService::class)
                ->attributeOnSignup($user, $refCode, $request->ip());
        } catch (\Throwable $e) {
            \Log::warning('Referral attribution failed: ' . $e->getMessage());
        }

        // New coaches get a free trial membership so they can immediately use
        // gated features (course creation, live classes, etc.) without paying
        // upfront. Admin can disable the trial by setting the plan inactive.
        if ($user->role === 'instructor') {
            try {
                $trial = app(\App\Services\MembershipService::class)->grantTrial($user);
                if ($trial) {
                    $user->notify(new \App\Notifications\CoachTrialWelcomeToUser($trial));
                }
            } catch (\Throwable $e) {
                \Log::warning('Grant coach free trial failed: ' . $e->getMessage());
            }
        }

        $settings = cache()->get('setting');
        $marketingSettings = cache()->get('marketing_setting');
        if ($user && $settings->google_tagmanager_status == 'active' && $marketingSettings->register) {
            $register_user = [
                'name' => $user->name,
                'email' => $user->email,
            ];
            session()->put('registerUser', $register_user);
        }

        (new MailSenderService)->sendVerifyMailToUserFromTrait('single_user', $user);
        
        $notification = __('A varification link has been send to your mail, please verify and enjoy our service');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);

    }

    public function custom_user_verification($token)
    {

        $user = User::where('verification_token', $token)->first();
        if ($user) {

            if ($user->email_verified_at != null) {
                $notification = __('Email already verified');
                $notification = ['messege' => $notification, 'alert-type' => 'error'];

                return redirect()->route('login')->with($notification);
            }

            $user->email_verified_at = date('Y-m-d H:i:s');
            $user->verification_token = null;
            $user->save();

            $notification = __('Verification successful please try to login now');
            $notification = ['messege' => $notification, 'alert-type' => 'success'];
            return redirect()->route('login')->with($notification);
        } else {
            $notification = __('Invalid token');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];

            return redirect()->route('register')->with($notification);
        }
    }
}
