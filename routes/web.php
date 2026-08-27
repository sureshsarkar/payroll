<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Webhook\StripeWebhookController;
use App\Http\Controllers\Webhook\RazorpayWebhookController;
use App\Http\Controllers\Webhook\BkashWebhookController;
use App\Http\Controllers\Webhook\PaypalWebhookController;
use App\Http\Controllers\Webhook\MercadoPagoWebhookController;
use App\Http\Controllers\Frontend\InstructorProfileSettingController;
use App\Http\Controllers\Frontend\StudentProfileSettingController;
use App\Http\Controllers\Frontend\TinymceImageUploadController;
use Illuminate\Support\Facades\Route;

// Public health check for uptime monitors (no maintenance-mode block — needs to work
// even during maintenance for the monitor to detect that you're maintenance-down).
Route::get('/up', \App\Http\Controllers\HealthCheckController::class)->name('healthcheck');

/* Payment webhooks — POST endpoints called by gateway servers, NOT browsers.
 * No auth, no CSRF (exempted in VerifyCsrfToken middleware). Each handler
 * verifies the gateway signature internally.
 *
 * Throttle policy:
 *  - Stripe / Razorpay / PayPal: HMAC or PayPal-API verified before any
 *    work. The signature check rejects 99.99% of garbage in <1ms, so a
 *    permissive throttle:120,1 is just a safety net against burst spam.
 *  - bKash / MercadoPago: NO inbound signature; the handler re-queries
 *    the gateway's API to verify. An attacker spamming these would burn
 *    our gateway API quota and our outbound HTTP timeouts. Tighter
 *    throttle:30,1 here. */
Route::post('webhooks/stripe',      [StripeWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')->name('webhooks.stripe');
Route::post('webhooks/razorpay',    [RazorpayWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')->name('webhooks.razorpay');
Route::post('webhooks/bkash',       [BkashWebhookController::class, 'handle'])
    ->middleware('throttle:30,1')->name('webhooks.bkash');
Route::post('webhooks/paypal',      [PaypalWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')->name('webhooks.paypal');
Route::post('webhooks/mercadopago', [MercadoPagoWebhookController::class, 'handle'])
    ->middleware('throttle:30,1')->name('webhooks.mercadopago');

/**
 * 2026-05-21 — Caddy SSL on-demand allow-list endpoint (Path B P6+).
 * Caddy calls this with ?domain=<host> before issuing a Let's
 * Encrypt cert; we return 200 only for hostnames that exist in
 * coach_domains with verified_at set, 403 otherwise. Prevents the
 * Let's Encrypt rate-limit DoS hazard documented in
 * docs/PATH_B_WHITELABEL_OPERATOR_GUIDE.md.
 *
 * Sits OUTSIDE the web middleware group — no session, no CSRF
 * (Caddy makes an unauthenticated GET). Rate-limited to deflect
 * mass-probing of the coach catalog.
 */
Route::get('internal/ssl-allowed', [\App\Http\Controllers\Internal\SslAllowlistController::class, 'check'])
    ->middleware('throttle:60,1')
    ->name('internal.ssl-allowed');

Route::group(['middleware' => 'maintenance.mode'], function () {

    Route::get('/clear-cache', function () {
        \Artisan::call('route:clear');
        \Artisan::call('config:clear');
        \Artisan::call('view:clear');

        return 'Cache cleared!';
    })->middleware('auth:admin');

    /**
     * ============================================================================
     * Global Routes
     * ============================================================================
     * LMS removal phase 2 (2026-08-27) — everything the storefront needed is
     * gone from here: the coach marketing subdomain/custom-domain routing, the
     * public course catalogue and course detail page, cart + coupon endpoints,
     * checkout, the blog, coach/trainer public profiles, the landing-page and
     * booking/trial/pricing enquiry endpoints, public certificate verification,
     * the section-builder marketing pages (home / about / contact / terms /
     * privacy / custom pages) and the whole Zoom live-class surface
     * (signature, status, attendance, lesson notes, instant meeting rooms).
     *
     * This is an internal HR/Payroll application now — there is no public
     * storefront, so `/` simply routes people to where they belong.
     */
    Route::get('set-language', [DashboardController::class, 'setLanguage'])->name('set-language');

    // Landing route. No marketing homepage any more: send authenticated users
    // to their own dashboard and everyone else to the login screen.
    Route::get('/', function () {
        if ($admin = auth('admin')->user()) {
            return redirect()->route('admin.dashboard');
        }
        if ($user = auth('web')->user()) {
            $isHr = $user->role === 'instructor' || !empty($user->coach_id);
            return redirect()->route($isHr ? 'hr.overview' : 'employee.overview');
        }
        return redirect()->route('login');
    })->name('home');

    /** other routes */
    Route::group(['prefix' => 'laravel-filemanager', 'middleware' => ['auth:admin'], 'as' => 'admin.'], function () {
        \UniSharp\LaravelFilemanager\Lfm::routes();
    });
    // V2 hardening (2026-06-16) — added `instructorrole` so the file manager is
    // reachable only by an HR user or their staff (employees are bounced).
    // Superadmin uses the separate admin file manager (/laravel-filemanager,
    // auth:admin). LFM remains per-user scoped (config/lfm.php:
    // allow_private_folder=true, allow_shared_folder=false) so one tenant
    // cannot browse another's files.
    Route::group(['prefix' => 'frontend-filemanager', 'as' => 'frontend.', 'middleware' => ['web', 'auth', 'verified', 'instructorrole']], function () {
        \UniSharp\LaravelFilemanager\Lfm::routes();
    });

    /**
     * ============================================================================
     * Employee (student-role) profile settings
     * ============================================================================
     * LMS removal phase 2 (2026-08-27) — this used to be the "Student Dashboard
     * Routes" block: LMS dashboard, enrolled courses, learning player, quizzes,
     * Q&A, reviews, orders, fees, live classes, announcements, certificates and
     * wishlist. All of that is gone. What survives is the account-settings
     * surface (profile / password / bio / education / experience / address /
     * socials), which is not LMS-specific and is the only place an employee can
     * change their own password. The `student.` name prefix is kept so the
     * shared settings views and partials keep resolving.
     * The employee's real workspace lives in Modules/{Payroll,Attendance,Leave}
     * under the `employee.` prefix.
     */
    Route::group(['middleware' => ['auth', 'verified', 'studentrole', '2fa:web'], 'prefix' => 'student', 'as' => 'student.'], function () {
        // Profile setting routes
        Route::get('setting', [StudentProfileSettingController::class, 'index'])->name('setting.index');
        Route::put('setting/profile', [StudentProfileSettingController::class, 'updateProfile'])->name('setting.profile.update');
        Route::put('setting/bio', [StudentProfileSettingController::class, 'updateBio'])->name('setting.bio.update');
        Route::put('setting/password', [StudentProfileSettingController::class, 'updatePassword'])->name('setting.password.update');
        Route::get('setting/experience-modal', [StudentProfileSettingController::class, 'showExperienceModal'])->name('setting.experience-modal');
        Route::get('setting/edit-experience-modal/{id}', [StudentProfileSettingController::class, 'editExperienceModal'])->name('setting.edit-experience-modal');

        Route::post('setting/experience', [StudentProfileSettingController::class, 'storeExperience'])->name('setting.experience.store');
        Route::put('setting/experience/{id}', [StudentProfileSettingController::class, 'updateExperience'])->name('setting.experience.update');
        Route::delete('setting/experience/{id}', [StudentProfileSettingController::class, 'destroyExperience'])->name('setting.experience.destroy');

        Route::get('setting/add-education-modal', [StudentProfileSettingController::class, 'addEducationModal'])->name('setting.add-education-modal');
        Route::post('setting/education', [StudentProfileSettingController::class, 'storeEducation'])->name('setting.education.store');
        Route::get('setting/edit-education-modal/{id}', [StudentProfileSettingController::class, 'editEducationModal'])->name('setting.edit-education-modal');
        Route::put('setting/education/{id}', [StudentProfileSettingController::class, 'updateEducation'])->name('setting.education.update');
        Route::delete('setting/education/{id}', [StudentProfileSettingController::class, 'destroyEducation'])->name('setting.education.destroy');

        Route::put('setting/address', [StudentProfileSettingController::class, 'updateAddress'])->name('setting.address.update');
        Route::put('setting/socials', [StudentProfileSettingController::class, 'updateSocials'])->name('setting.socials.update');
    });

    /**
     * ============================================================================
     * HR (instructor-role) profile settings
     * ============================================================================
     * LMS removal phase 2 (2026-08-27) — this used to be the ~670-line
     * "Instructor Dashboard Routes" block: the coach dashboard, courses,
     * chapters/lessons/quizzes, course batches, live classes, instant meetings,
     * lesson Q&A, orders/sells, coupons, fees, offline payments, payouts,
     * subscriptions, announcements, certificate builder, coach staff + roles +
     * permissions, tax settings, trainers, temporary slots, trial sessions,
     * pricing enquiries, blogs, email templates/preview, brand settings, custom
     * domains, analytics, reports and the whole website builder / coach-site
     * page editor. All of it is gone.
     *
     * What survives is the account-settings surface (profile / password / bio /
     * education / experience / address / socials) — not LMS-specific, and the
     * only place an HR user can change their own password. The `instructor.`
     * name prefix is kept so the shared settings views and partials keep
     * resolving. `requires.membership` is dropped: membership was the LMS
     * coach-subscription gate and must never stand between an HR user and
     * their own password.
     *
     * HR's real workspace lives in Modules/{Company,HrEmployee,Payroll,
     * Attendance,Leave} under the `hr.` prefix.
     */
    Route::group(['middleware' => ['auth', 'verified', 'instructorrole', '2fa:web'], 'prefix' => 'instructor', 'as' => 'instructor.'], function () {
        Route::get('setting', [InstructorProfileSettingController::class, 'index'])->name('setting.index');
        Route::put('setting/profile', [InstructorProfileSettingController::class, 'updateProfile'])->name('setting.profile.update');
        Route::put('setting/bio', [InstructorProfileSettingController::class, 'updateBio'])->name('setting.bio.update');
        Route::put('setting/password', [InstructorProfileSettingController::class, 'updatePassword'])->name('setting.password.update');
        Route::get('setting/experience-modal', [InstructorProfileSettingController::class, 'showExperienceModal'])->name('setting.experience-modal');
        Route::get('setting/edit-experience-modal/{id}', [InstructorProfileSettingController::class, 'editExperienceModal'])->name('setting.edit-experience-modal');
        Route::post('setting/experience', [InstructorProfileSettingController::class, 'storeExperience'])->name('setting.experience.store');
        Route::put('setting/experience/{id}', [InstructorProfileSettingController::class, 'updateExperience'])->name('setting.experience.update');
        Route::delete('setting/experience/{id}', [InstructorProfileSettingController::class, 'destroyExperience'])->name('setting.experience.destroy');
        Route::get('setting/add-education-modal', [InstructorProfileSettingController::class, 'addEducationModal'])->name('setting.add-education-modal');
        Route::post('setting/education', [InstructorProfileSettingController::class, 'storeEducation'])->name('setting.education.store');
        Route::get('setting/edit-education-modal/{id}', [InstructorProfileSettingController::class, 'editEducationModal'])->name('setting.edit-education-modal');
        Route::put('setting/education/{id}', [InstructorProfileSettingController::class, 'updateEducation'])->name('setting.education.update');
        Route::delete('setting/education/{id}', [InstructorProfileSettingController::class, 'destroyEducation'])->name('setting.education.destroy');
        Route::put('setting/address', [InstructorProfileSettingController::class, 'updateAddress'])->name('setting.address.update');
        Route::put('setting/socials', [InstructorProfileSettingController::class, 'updateSocials'])->name('setting.socials.update');
    });

    Route::group(['middleware' => ['auth', 'verified']], function () {
        // LMS removal phase 2 (2026-08-27) — the wishlist / secure-video /
        // checkout / affiliate / referral / membership / course-announcement
        // routes that used to live here went with the LMS. What is left is the
        // genuinely cross-cutting account surface: push subscriptions, the
        // notification bell, notification preferences and 2FA.
        Route::post('tinymce-upload-image', [TinymceImageUploadController::class, 'upload']);
        Route::delete('tinymce-delete-image', [TinymceImageUploadController::class, 'destroy']);

        // Browser-push subscription save/delete (used by /global/push/push.js)
        Route::post('push/subscribe',   [\App\Http\Controllers\Frontend\PushSubscriptionController::class, 'store']);
        Route::delete('push/subscribe', [\App\Http\Controllers\Frontend\PushSubscriptionController::class, 'destroy']);

        // Bell-icon notification endpoints — shared by HR + employee dashboards.
        Route::controller(\App\Http\Controllers\Frontend\NotificationController::class)
            ->prefix('notifications')->name('notifications.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('recent', 'recent')->name('recent');
                Route::get('unread-count', 'unreadCount')->name('unread-count');
                Route::post('{id}/read', 'markRead')->name('mark-read');
                Route::post('read-all', 'markAllRead')->name('mark-all-read');
                Route::delete('{id}', 'destroy')->name('destroy');
            });

        // Per-user notification preferences (channels × events)
        Route::controller(\App\Http\Controllers\Frontend\NotificationPreferenceController::class)
            ->prefix('notifications/preferences')->name('notifications.preferences.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'update')->name('update');
            });

        /* 2FA — challenge endpoints (must be reachable AFTER login but BEFORE
           the 2fa:web middleware can pass). Session must be active.
           Throttle: 6-digit TOTP has only 10^6 codes (3M with ±1 skew window),
           so unbounded attempts = trivial bruteforce once the password is known. */
        Route::controller(\App\Http\Controllers\Frontend\TwoFactorController::class)->group(function () {
            Route::get('2fa/challenge',           'showChallenge')->name('web.2fa.challenge');
            Route::post('2fa/challenge/verify',   'verifyChallenge')
                ->middleware('throttle:5,1')->name('web.2fa.challenge.verify');
            Route::post('2fa/challenge/recovery', 'useRecovery')
                ->middleware('throttle:5,1')->name('web.2fa.challenge.recovery');
        });

        /* 2FA — setup/management. Gated by 2fa:web so an enrolled user can't
           bypass the challenge by going directly to /2fa/setup. */
        Route::middleware('2fa:web')->controller(\App\Http\Controllers\Frontend\TwoFactorController::class)->group(function () {
            Route::get('2fa/setup',      'showSetup')->name('web.2fa.setup');
            Route::post('2fa/enable',    'enable')->middleware('throttle:10,1')->name('web.2fa.enable');
            Route::post('2fa/confirm',   'confirm')->middleware('throttle:5,1')->name('web.2fa.confirm');
            Route::post('2fa/disable',   'disable')->middleware('throttle:5,1')->name('web.2fa.disable');
            Route::post('2fa/regenerate','regenerateRecovery')->middleware('throttle:5,1')->name('web.2fa.regenerate');
        });
    });
});

// maintenance mode route
Route::get('/maintenance-mode', function () {
    $setting = Illuminate\Support\Facades\Cache::get('setting', null);
    if (! $setting?->maintenance_mode) {
        return redirect()->route('home');
    }

    return view('global.maintenance');
})->name('maintenance.mode');

require __DIR__.'/auth.php';

require __DIR__.'/admin.php';

/*
|--------------------------------------------------------------------------
| Fallback
|--------------------------------------------------------------------------
| LMS removal phase 2 (2026-08-27) — this used to be the white-label
| custom-domain fallback (G1b, 2026-06-04): on a verified coach custom domain
| it served the coach's marketing sub-pages at the root. There are no coach
| marketing sites any more, so an unmatched route is simply a 404 again.
*/
Route::fallback(function () {
    abort(404);
});
