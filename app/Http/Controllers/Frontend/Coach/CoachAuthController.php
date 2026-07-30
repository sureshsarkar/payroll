<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Frontend\Coach\Traits\BuildsCoachSiteContext;
use App\Models\CoachStudentLink;
use App\Models\User;
use App\Services\BrandResolver;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Coach-scoped authentication.
 *
 * Renders login + register inside the coach's master layout. After
 * successful auth the student lands on the FULL platform student panel
 * (route('student.dashboard')) — which, on a coach domain, scopes every
 * query to this coach and inherits the coach's brand via the brand-aware
 * layout (updated 2026-06-10; previously this went to the thin
 * /coach/{slug}/student/dashboard surface). The session also receives a
 * tenant_coach_id so PaymentFulfilmentService can attribute the buyer
 * to the right coach if they go straight to checkout.
 *
 * For register, a CoachStudentLink row is created so the new student
 * immediately appears in the coach's /instructor/students roster.
 *
 * Business-logic alignment with platform auth controllers:
 *   - same email + password rules
 *   - same UserStatus / is_banned checks
 *   - same email-verification gate
 *   - same session-fixation defense (regenerate)
 *   - same sessionCartToDatabase() merge
 *   - 2FA path: if user has it enabled, redirect to the platform
 *     2FA challenge (one shared screen) and then back to coach dashboard
 */
class CoachAuthController extends Controller
{
    use BuildsCoachSiteContext;

    public function showLogin(Request $request, string $coachSlug)
    {
        $coach = $request->attributes->get('tenant_coach');
        if (! $coach) {
            return redirect()->route('login');
        }

        $brand = app(BrandResolver::class)->forCoach((int) $coach->id);

        return view('frontend.coach-site.pages.login', [
            'coachSlug'        => $coachSlug,
            'coach'            => $coach,
            'brand'            => $brand,
            'page'             => $this->syntheticPage($coach, 'login', __('Log in')),
            'siteNav'          => $this->siteNavFor($coach, $coachSlug),
            'hasFooterSection' => false,
            'bodyHtml'         => null,
        ]);
    }

    public function login(Request $request, string $coachSlug)
    {
        $coach = $request->attributes->get('tenant_coach');
        if (! $coach) {
            return redirect()->route('login');
        }

        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required'    => __('Email is required'),
            'password.required' => __('Password is required'),
        ]);

        $user = User::where('email', $request->email)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('Invalid credentials please check your email and password'),
            ]);
        }
        if ($user->status != UserStatus::ACTIVE->value) {
            throw ValidationException::withMessages([
                'email' => __('Inactive account'),
            ]);
        }
        if ($user->is_banned == UserStatus::BANNED->value) {
            return redirect()->back()->with([
                'messege'    => __('Your account has been banned'),
                'alert-type' => 'error',
            ]);
        }
        if (! $user->email_verified_at) {
            return redirect()->back()->with([
                'messege'    => __('Please verify your email'),
                'alert-type' => 'error',
            ]);
        }

        // TENANT ISOLATION (2026-06-16) — a student may only authenticate on a
        // coach site they actually belong to (coach_student_links). A coach /
        // staff member may only sign in on their own surface. This blocks
        // Coach A's student from logging in under Coach B. Checked BEFORE the
        // session is created, so no foreign session is ever established.
        if (! \App\Support\TenantAccess::userMayAccessCoach($user, (int) $coach->id)) {
            throw ValidationException::withMessages([
                'email' => __('These credentials are not authorized for this website.'),
            ]);
        }

        if (! Auth::guard('web')->attempt(
            ['email' => $request->email, 'password' => $request->password],
            (bool) $request->remember
        )) {
            throw ValidationException::withMessages([
                'email' => __('Invalid credentials please check your email and password'),
            ]);
        }

        // Session-fixation defense
        $request->session()->regenerate();
        $request->session()->forget('two_factor.passed_at.web');

        // 2FA — uses the platform challenge screen but we stash the
        // post-challenge destination so the redirect lands on the right
        // panel for the role (2026-06-11: was hardcoded to the student
        // panel, which sent a COACH logging in on their own site to the
        // student dashboard).
        $loggedUser = Auth::guard('web')->user();
        if ($loggedUser && method_exists($loggedUser, 'hasTwoFactorEnabled') && $loggedUser->hasTwoFactorEnabled()) {
            $request->session()->put('url.intended', $loggedUser->role === 'student'
                ? route('student.dashboard')
                : route('instructor.dashboard'));
            return redirect()->route('web.2fa.challenge');
        }

        // Merge session cart into DB so anonymous-cart items survive login
        $cartCount = Cart::content()->count();
        if (function_exists('sessionCartToDatabase')) {
            sessionCartToDatabase();
        }

        // Re-stamp tenant context so downstream pages know we're coach-scoped
        $request->session()->put('tenant_coach_id', (int) $coach->id);

        $notification = ['messege' => __('Logged in successfully.'), 'alert-type' => 'success'];

        // 2026-06-11 — role-aware landing (the "coach AND student can login
        // using the custom website" requirement). A coach/staff member logging
        // in on their own site goes to the instructor panel — the cart/checkout
        // shortcut applies only to students (coaches can't buy own courses).
        $role = Auth::guard('web')->user()->role ?? 'student';
        if ($role !== 'student') {
            return redirect()->route('instructor.dashboard')->with($notification);
        }

        // Cart has items → coach-branded checkout. Otherwise the full
        // (coach-scoped) student panel.
        if ($cartCount > 0) {
            return redirect()->route('coach.checkout', ['coachSlug' => $coachSlug])->with($notification);
        }
        return redirect()->route('student.dashboard')->with($notification);
    }

    public function showRegister(Request $request, string $coachSlug)
    {
        $coach = $request->attributes->get('tenant_coach');
        if (! $coach) {
            return redirect()->route('register');
        }

        $brand = app(BrandResolver::class)->forCoach((int) $coach->id);

        return view('frontend.coach-site.pages.register', [
            'coachSlug'        => $coachSlug,
            'coach'            => $coach,
            'brand'            => $brand,
            'page'             => $this->syntheticPage($coach, 'register', __('Create your account')),
            'siteNav'          => $this->siteNavFor($coach, $coachSlug),
            'hasFooterSection' => false,
            'bodyHtml'         => null,
        ]);
    }

    public function register(Request $request, string $coachSlug)
    {
        $coach = $request->attributes->get('tenant_coach');
        if (! $coach) {
            return redirect()->route('register');
        }

        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', 'min:8', 'max:100'],
        ], [
            'name.required'      => __('Name is required'),
            'email.required'     => __('Email is required'),
            'email.unique'       => __('Email already exist'),
            'password.required'  => __('Password is required'),
            'password.confirmed' => __('Confirm password does not match'),
            'password.min'       => __('You have to provide minimum 8 character password'),
        ]);

        $username = Str::slug($request->name);
        $lastId   = User::latest()->value('id');

        $user = User::create([
            'coach_unique_id'    => 'MBS' . $lastId,
            'role'               => 'student',
            'name'               => $request->name,
            'username'           => $username,
            'email'              => $request->email,
            'status'             => 'active',
            'is_banned'          => 'no',
            'password'           => Hash::make($request->password),
            'verification_token' => Str::random(100),
            // Auto-verify when registering through a coach site so the
            // student can immediately checkout. This mirrors the trust
            // model: the coach vouches for the audience that visited
            // their domain. Coaches can revoke a student later if abuse.
            'email_verified_at'  => now(),
        ]);

        // Immediately attribute the new student to THIS coach. Source
        // 'invite' captures intent: "this student arrived via my brand".
        // CoachStudentLink::link() is idempotent — repeat signups are safe.
        try {
            CoachStudentLink::link((int) $coach->id, (int) $user->id, 'invite');
        } catch (\Throwable $e) {
            \Log::warning('coach-register-link-failed', [
                'coach_id'   => $coach->id,
                'student_id' => $user->id,
                'error'      => $e->getMessage(),
            ]);
        }

        // Feature 1 + 2 (2026-06-26) — fire the welcome email to the student and
        // a new-registration notification to the coach. Both go through the
        // coach's tenant SMTP + branding (BrandedNotificationMail) with platform
        // fallback, and are auto-logged in notification_email_logs. Wrapped so a
        // mail/SMTP failure can NEVER break or block the registration flow.
        try {
            $orgName = optional(app(\App\Services\BrandResolver::class)->forCoach((int) $coach->id))->name
                ?: ($coach->name ?? config('app.name'));
            $loginUrl = route('coach.login', ['coachSlug' => $coachSlug]);

            $user->notify(new \App\Notifications\StudentWelcomeToStudent($coach, (string) $orgName, $loginUrl));
            $coach->notify(new \App\Notifications\NewStudentRegisteredToCoach(
                $user,
                (string) $orgName,
                now()->format('d M Y, h:i A')
            ));
        } catch (\Throwable $e) {
            \Log::warning('coach-register-email-failed', [
                'coach_id'   => $coach->id,
                'student_id' => $user->id,
                'error'      => $e->getMessage(),
            ]);
        }

        // Auto-login so the next click goes straight into checkout
        Auth::guard('web')->loginUsingId($user->id);
        $request->session()->regenerate();
        $request->session()->put('tenant_coach_id', (int) $coach->id);

        if (function_exists('sessionCartToDatabase')) {
            sessionCartToDatabase();
        }

        $cartCount = Cart::content()->count() + (auth()->check() ? (int) auth()->user()->cart_count : 0);
        $notification = ['messege' => __('Welcome! Your account is ready.'), 'alert-type' => 'success'];

        if ($cartCount > 0) {
            return redirect()->route('coach.checkout', ['coachSlug' => $coachSlug])->with($notification);
        }
        return redirect()->route('student.dashboard')->with($notification);
    }

    public function logout(Request $request, string $coachSlug)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Logout returns the student to the coach's marketing home — they
        // remain on the coach's brand, not on platform's /.
        return redirect()->route('coach.site.path', ['site_slug' => $coachSlug])
            ->with(['messege' => __('Logged out successfully.'), 'alert-type' => 'success']);
    }
}
