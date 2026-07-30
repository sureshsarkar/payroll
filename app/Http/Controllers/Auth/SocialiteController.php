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

            // TENANT ISOLATION (2026-06-16) — coach surface this OAuth callback
            // landed on (0 = platform). Used to (a) block an existing student of
            // another coach from social-logging-in here, and (b) auto-attribute
            // a brand-new social user to this coach. See \App\Support\TenantAccess.
            $coachId = (int) (request()->attributes->get('resolved_coach_id') ?? 0);

            // PLATFORM CONFINEMENT (2026-06-16) — an existing student who belongs
            // to a coach may not sign in on the bare platform; send them to their
            // coach website. (New users / platform-native students fall through.)
            if ($user && $coachId === 0 && (string) ($user->role ?? '') === 'student') {
                $confineUrl = \App\Support\TenantAccess::confineUrlForStudent($user);
                if ($confineUrl) {
                    return redirect()->to($confineUrl)->with([
                        'messege'    => __('Please sign in on your coach\'s website to access your dashboard.'),
                        'alert-type' => 'info',
                    ]);
                }
            }
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
                                if ($coachId > 0 && ! \App\Support\TenantAccess::userMayAccessCoach($user, $coachId)) {
                                    return redirect()->to('/')->with([
                                        'messege'    => __('These credentials are not authorized for this website.'),
                                        'alert-type' => 'error',
                                    ]);
                                }
                                Auth::guard('web')->login($user, true);
                                // SECURITY (audit 2026-05-22) — regenerate
                                // session ID on every login to prevent
                                // session-fixation. Mirror of what the
                                // email/password login flow already does.
                                request()->session()->regenerate();
                                $notification = __('Logged in successfully.');
                                $notification = ['messege' => $notification, 'alert-type' => 'success'];

                                return redirect()
                                    ->intended(route('student.dashboard'))
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
                        if ($coachId > 0 && ! \App\Support\TenantAccess::userMayAccessCoach($user, $coachId)) {
                            return redirect()->to('/')->with([
                                'messege'    => __('These credentials are not authorized for this website.'),
                                'alert-type' => 'error',
                            ]);
                        }
                        Auth::guard('web')->login($user, true);
                        // SECURITY (audit 2026-05-22) — see L65 comment.
                        request()->session()->regenerate();
                        $notification = __('Logged in successfully.');
                        $notification = ['messege' => $notification, 'alert-type' => 'success'];

                        // user.dashboard route doesn't exist; pick the correct
                        // dashboard from the user's role.
                        $dashboard = $user->role === 'student'
                            ? route('student.dashboard')
                            : route('instructor.dashboard');

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
                        // Brand-new social user on a coach surface → attribute
                        // them to that coach (mirrors coach-site registration).
                        \App\Support\TenantAccess::autoLinkIfStudent($user, $coachId, 'invite');
                        Auth::guard('web')->login($user, true);
                        // SECURITY (audit 2026-05-22) — see L65 comment.
                        request()->session()->regenerate();
                        $notification = __('Logged in successfully.');
                        $notification = ['messege' => $notification, 'alert-type' => 'success'];

                        return redirect()
                            ->intended(route('student.dashboard'))
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
