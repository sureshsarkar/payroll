<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialiteDriverType;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\NewUserCreateTrait;
use App\Traits\SetConfigTrait;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller {
    use NewUserCreateTrait, SetConfigTrait;

    public function __construct() {
        $driver = request('driver', null);
        if ($driver == SocialiteDriverType::FACEBOOK->value) {
            self::setFacebookLoginInfo();
        } elseif ($driver == SocialiteDriverType::GOOGLE->value) {
            self::setGoogleLoginInfo();
        }
    }

    public function redirectToDriver($driver) {
        if (in_array($driver, SocialiteDriverType::getAll())) {
            return Socialite::driver($driver)->redirect();
        }
        $notification = __('Invalid Social Login Type!');
        $notification = ['messege' => $notification, 'alert-type' => 'error'];

        return redirect()->back()->with($notification);
    }

    public function handleDriverCallback($driver) {
        if (!in_array($driver, SocialiteDriverType::getAll())) {
            $notification = __('Invalid Social Login Type!');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];

            return redirect()->back()->with($notification);
        }
        try {
            $provider_name = SocialiteDriverType::from($driver)->value;
            $callbackUser = Socialite::driver($provider_name)->stateless()->user();
            $user = User::where('email', $callbackUser->getEmail())->first();

            // LMS removal phase 2 (2026-08-27) — dropped the two white-label
            // OAuth guards (\App\Support\TenantAccess): the coach-surface tenant
            // check, and the confinement redirect that sent an existing student
            // to their coach's own site instead of signing them in here.
            if ($user) {
                $findDriver = $user
                    ->socialite()
                    ->where(['provider_name' => $provider_name, 'provider_id' => $callbackUser->getId()])
                    ->first();

                if ($findDriver) {
                    if ($user->status == UserStatus::ACTIVE->value) {
                        if ($user->is_banned == UserStatus::UNBANNED->value) {
                            if (app()->isProduction() && $user->email_verified_at == null) {
                                $notification = __('Please verify your email');
                                $notification = ['messege' => $notification, 'alert-type' => 'error'];

                                return redirect()
                                    ->back()
                                    ->with($notification);
                            }
                            if ($findDriver) {
                                Auth::guard('web')->login($user, true);
                                // SECURITY (audit 2026-05-22) — regenerate
                                // session ID on every login to prevent
                                // session-fixation. Mirror of what the
                                // email/password login flow already does.
                                request()->session()->regenerate();
                                $notification = __('Logged in successfully.');
                                $notification = ['messege' => $notification, 'alert-type' => 'success'];

                                return redirect()
                                    ->intended(route('employee.overview'))
                                    ->with($notification);
                            }
                        } else {
                            $notification = __('Inactive account');
                            $notification = ['messege' => $notification, 'alert-type' => 'error'];

                            return redirect()
                                ->back()
                                ->with($notification);
                        }
                    } else {
                        $notification = __('Inactive account');
                        $notification = ['messege' => $notification, 'alert-type' => 'error'];

                        return redirect()
                            ->back()
                            ->with($notification);
                    }
                } else {
                    $socialite = $this->createNewUser(callbackUser: $callbackUser, provider_name: $provider_name, user: $user);

                    if ($socialite) {
                        Auth::guard('web')->login($user, true);
                        // SECURITY (audit 2026-05-22) — see L65 comment.
                        request()->session()->regenerate();
                        $notification = __('Logged in successfully.');
                        $notification = ['messege' => $notification, 'alert-type' => 'success'];

                        // user.dashboard route doesn't exist; pick the correct
                        // dashboard from the user's role. LMS→HR conversion —
                        // this install has no LMS features exposed any more,
                        // so employees/HR land on their own dashboards.
                        $dashboard = $user->role === 'student'
                            ? route('employee.overview')
                            : route('hr.dashboard');

                        return redirect()->intended($dashboard)->with($notification);
                    }

                    $notification = __('Login Failed');
                    $notification = ['messege' => $notification, 'alert-type' => 'error'];

                    return redirect()
                        ->back()
                        ->with($notification);
                }
            } else {
                if ($callbackUser) {
                    $socialite = $this->createNewUser(callbackUser: $callbackUser, provider_name: $provider_name, user: false);
                    if ($socialite) {
                        $user = User::find($socialite->user_id);
                        Auth::guard('web')->login($user, true);
                        // SECURITY (audit 2026-05-22) — see L65 comment.
                        request()->session()->regenerate();
                        $notification = __('Logged in successfully.');
                        $notification = ['messege' => $notification, 'alert-type' => 'success'];

                        return redirect()
                            ->intended(route('employee.overview'))
                            ->with($notification);
                    }

                    $notification = __('Login Failed');
                    $notification = ['messege' => $notification, 'alert-type' => 'error'];

                    return redirect()
                        ->back()
                        ->with($notification);
                }

                $notification = __('Login Failed');
                $notification = ['messege' => $notification, 'alert-type' => 'error'];

                return redirect()->back()->with($notification);
            }

        } catch (\Exception $e) {
            return to_route('login');
        }
    }
}
