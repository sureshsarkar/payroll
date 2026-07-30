<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Global\CloudStorageController;
use App\Http\Controllers\Admin\Auth\NewPasswordController;
use App\Http\Controllers\Admin\Auth\PasswordResetLinkController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\TwoFactorController;
use App\Http\Controllers\Admin\ReferralCommissionController;


 



Route::group(['as' => 'admin.', 'prefix' => 'admin'], function () {

    /* Start admin auth route */
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('store-login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('store-login');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forget-password', [PasswordResetLinkController::class, 'custom_forget_password'])->middleware('throttle:5,1')->name('forget-password');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'custom_reset_password_page'])->name('password.reset');
    Route::post('/reset-password-store/{token}', [NewPasswordController::class, 'custom_reset_password_store'])->middleware('throttle:6,1')->name('password.reset-store');
    /* End admin auth route */

    /* 2FA challenge routes — must be reachable AFTER login but BEFORE the
       2fa middleware can pass. They're inside auth:admin but outside the
       2fa-gated group below. */
    Route::middleware(['auth:admin'])->group(function () {
        Route::get('2fa/challenge',           [TwoFactorController::class, 'showChallenge'])->name('2fa.challenge');
        // Tight throttle on the verify endpoints: 6-digit TOTP has 10^6 = 1M
        // codes, with a ±1 window of skew the effective space is 3M.
        // Unbounded attempts = trivial bruteforce once the password is known.
        // 5 attempts/min per IP+route is enough for legit clock-skew retries.
        Route::post('2fa/challenge/verify',   [TwoFactorController::class, 'verifyChallenge'])
            ->middleware('throttle:5,1')->name('2fa.challenge.verify');
        Route::post('2fa/challenge/recovery', [TwoFactorController::class, 'useRecovery'])
            ->middleware('throttle:5,1')->name('2fa.challenge.recovery');
    });

    /* Everything else requires both an authenticated admin AND a passed 2FA
       challenge (when 2FA is enabled on that admin's account). */
    Route::middleware(['auth:admin', '2fa:admin'])->group(function () {
        // Cache-clear is handled by the GlobalSetting module at
        // admin.cache-clear / admin.cache-clear-confirm (GET to show
        // confirmation form, POST to actually clear). The shadow GET
        // closure that used to live here ran cache:clear + route:clear
        // + config:clear + view:clear with NO permission check and via
        // a mutating GET — removed 2026-05-12 as audit finding C1.

        Route::get('/', [DashboardController::class, 'dashboard']);
        Route::get('dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');

        // Enterprise H-A — read-only activity / audit log viewer (Super Admin).
        Route::get('activity-logs', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])
            ->name('activity-logs');

        // 2026-07-14 — read-only cross-coach booking enquiries (Pricing & Schedule).
        Route::get('booking-enquiries', [\App\Http\Controllers\Admin\BookingEnquiryController::class, 'index'])
            ->name('booking-enquiries');

        // ── Theme Studio (Phase 2 — Super Admin theme catalog) ────────────
        // Curate the themes that coaches can pick during onboarding.
        Route::prefix('themes')->name('themes.')->group(function () {
            Route::get('/',                     [\App\Http\Controllers\Admin\ThemeController::class, 'index'])->name('index');
            Route::get('create',                [\App\Http\Controllers\Admin\ThemeController::class, 'create'])->name('create');
            Route::post('/',                    [\App\Http\Controllers\Admin\ThemeController::class, 'store'])->name('store');
            Route::get('{id}/edit',             [\App\Http\Controllers\Admin\ThemeController::class, 'edit'])->name('edit')->where('id', '[0-9]+');
            Route::put('{id}',                  [\App\Http\Controllers\Admin\ThemeController::class, 'update'])->name('update')->where('id', '[0-9]+');
            Route::post('{id}/toggle',          [\App\Http\Controllers\Admin\ThemeController::class, 'toggle'])->name('toggle')->where('id', '[0-9]+');
            Route::post('{id}/duplicate',       [\App\Http\Controllers\Admin\ThemeController::class, 'duplicate'])->name('duplicate')->where('id', '[0-9]+');
            Route::delete('{id}',               [\App\Http\Controllers\Admin\ThemeController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
            Route::get('{id}/preview',          [\App\Http\Controllers\Admin\ThemeController::class, 'preview'])->name('preview')->where('id', '[0-9]+');
            Route::get('{id}/usage',            [\App\Http\Controllers\Admin\ThemeController::class, 'usage'])->name('usage')->where('id', '[0-9]+');

            // Categories (sub-group keeps URL clean as /admin/themes/categories)
            Route::prefix('categories')->name('categories.')->group(function () {
                Route::get('/',                 [\App\Http\Controllers\Admin\ThemeCategoryController::class, 'index'])->name('index');
                Route::post('/',                [\App\Http\Controllers\Admin\ThemeCategoryController::class, 'store'])->name('store');
                Route::put('{id}',              [\App\Http\Controllers\Admin\ThemeCategoryController::class, 'update'])->name('update')->where('id', '[0-9]+');
                Route::delete('{id}',           [\App\Http\Controllers\Admin\ThemeCategoryController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
            });
        });

        Route::controller(AdminProfileController::class)->group(function () {
            Route::get('edit-profile', 'edit_profile')->name('edit-profile');
            Route::put('profile-update', 'profile_update')->name('profile-update');
            Route::put('update-password', 'update_password')->name('update-password');
        });

        Route::get('role/assign', [RolesController::class, 'assignRoleView'])->name('role.assign');
        Route::post('role/assign/{id}', [RolesController::class, 'getAdminRoles'])->name('role.assign.admin');
        Route::put('role/assign', [RolesController::class, 'assignRoleUpdate'])->name('role.assign.update');
        Route::resource('/role', RolesController::class);

        Route::resource('admin', AdminController::class)->except('show');
        Route::put('admin-status/{id}', [AdminController::class, 'changeStatus'])->name('admin.status');

        /*
         * Audit 2026-05-18 — admin-side per-batch attendance summary.
         * Renders cards showing Total / Attended / Not Attended for a batch
         * on a chosen date (defaults to today).
         */
        Route::get('batches', [\App\Http\Controllers\Admin\BatchAttendanceController::class, 'index'])
            ->name('batches.index');

        // Audit 2026-05-18 phase 5 — attendance verification settings page.
        Route::get('attendance-settings', [\App\Http\Controllers\Admin\AttendanceSettingsController::class, 'show'])
            ->name('attendance-settings.show');
        Route::put('attendance-settings', [\App\Http\Controllers\Admin\AttendanceSettingsController::class, 'update'])
            ->name('attendance-settings.update');

        // Audit 2026-05-18 Req 3 — per-coach commission management.
        Route::get('coach-commissions', [\App\Http\Controllers\Admin\CoachCommissionController::class, 'index'])
            ->name('coach-commissions.index');
        Route::get('coach-commissions/{id}/edit', [\App\Http\Controllers\Admin\CoachCommissionController::class, 'edit'])
            ->whereNumber('id')
            ->name('coach-commissions.edit');
        Route::put('coach-commissions/{id}', [\App\Http\Controllers\Admin\CoachCommissionController::class, 'update'])
            ->whereNumber('id')
            ->name('coach-commissions.update');
        Route::get('batch-attendance/{batch}', [\App\Http\Controllers\Admin\BatchAttendanceController::class, 'show'])
            ->whereNumber('batch')
            ->name('batch-attendance.show');

        // 2026-07-03 — cross-coach Trial Session enquiries & payments (read-only).
        Route::get('trial-sessions', [\App\Http\Controllers\Admin\TrialSessionAdminController::class, 'index'])
            ->name('trial-sessions.index');

        /*
         * Audit 2026-05-18 — admin oversight of coach announcements.
         *   GET  /admin/announcements                  index (with filters)
         *   GET  /admin/announcements/{id}             show
         *   PUT  /admin/announcements/{id}/toggle      activate/deactivate
         *   DELETE /admin/announcements/{id}           destroy
         *
         * Permission gates inside the controller (announcement.view /
         * .toggle-status / .delete) — seeded by the same-date migration.
         */
        Route::controller(\App\Http\Controllers\Admin\AnnouncementController::class)
            ->prefix('announcements')
            ->name('announcements.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                // Audit 2026-05-20 — admin can now create + edit (was read-only).
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/batches-for-course/{course}', 'batchesForCourse')
                     ->whereNumber('course')->name('batches-for-course');
                Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
                Route::put('/{id}', 'update')->whereNumber('id')->name('update');
                Route::get('/{id}', 'show')->whereNumber('id')->name('show');
                Route::put('/{id}/toggle', 'toggleStatus')->whereNumber('id')->name('toggle');
                Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
            });
        // Settings routes
        Route::get('settings', [SettingController::class, 'settings'])->name('settings');
        Route::post('cloud/store', [CloudStorageController::class, 'store'])->name('cloud.store');

        /* Zoom OAuth health dashboard (added 2026-05-07 after silent
           token-expiry incident — see ZoomHealthCheck command). */
        Route::controller(\App\Http\Controllers\Admin\ZoomHealthController::class)
            ->prefix('zoom-health')
            ->name('zoom-health.')
            ->group(function () {
                Route::get('/',                'index')->name('index');
                Route::post('probe',           'probe')->name('probe-all');
                Route::post('probe/{instructor_id}', 'probe')->name('probe-one');
            });

        /* Coach landing-page oversight — Phase 5 of the multi-template
           business website system (2026-05-12). Cross-coach view of every
           published page, with template + business-vertical attribution
           and per-page enquiry drill-down. */
        Route::controller(\App\Http\Controllers\Admin\AdminLandingPageController::class)
            ->prefix('coach-landing-pages')
            ->name('coach-landing-pages.')
            ->group(function () {
                Route::get('/',                  'index')->name('index');
                // Phase 7 — register the analytics route BEFORE the /{id}/...
                // pattern, otherwise `templates-report` would be parsed as
                // an integer id and 404.
                Route::get('templates-report',   'templatePerformance')->name('templates-report');
                Route::get('{id}/enquiries',     'enquiries')->whereNumber('id')->name('enquiries');
            });

        /* Referral commissions (legacy — coach commission stream paid into cash wallet) */
        Route::get('referral-commissions',                 [ReferralCommissionController::class, 'index'])->name('referral-commissions.index');
        Route::post('referral-commissions/{id}/approve',   [ReferralCommissionController::class, 'approve'])->name('referral-commissions.approve');
        Route::post('referral-commissions/{id}/pay',       [ReferralCommissionController::class, 'pay'])->name('referral-commissions.pay');
        Route::post('referral-commissions/{id}/reverse',   [ReferralCommissionController::class, 'reverse'])->name('referral-commissions.reverse');
        Route::post('referral-commissions/percent',        [ReferralCommissionController::class, 'updatePercent'])->name('referral-commissions.percent');

        /* Membership plans (admin CRUD) */
        // 2026-05-26 (bug-doc A1) — bind the route segment to `{plan}` so it
        // matches the controller methods' `MembershipPlan $plan` parameter.
        // Without this, Route::resource defaulted to `{membership_plan}` →
        // implicit model binding failed → empty model handed to destroy() →
        // delete() was a no-op while the controller cheerfully returned
        // "Plan deleted" success.
        Route::resource('membership-plans', \App\Http\Controllers\Admin\MembershipPlanController::class)
            ->except(['show'])
            ->parameters(['membership-plans' => 'plan'])
            ->names('membership-plans');

        /* 2026-06-25 — configurable coach free-trial settings */
        Route::get('coach-trial-settings', [\App\Http\Controllers\Admin\CoachTrialSettingController::class, 'edit'])->name('coach-trial-settings.edit');
        Route::post('coach-trial-settings', [\App\Http\Controllers\Admin\CoachTrialSettingController::class, 'update'])->name('coach-trial-settings.update');

        /* User memberships (admin view + manual confirm) */
        Route::controller(\App\Http\Controllers\Admin\UserMembershipController::class)
            ->prefix('user-memberships')->name('user-memberships.')->group(function () {
                Route::get('/',                     'index')->name('index');
                Route::get('conversion',            'conversionReport')->name('conversion');
                // 2026-06-24 (Phase 4) — assign/change a coach's plan + coach billing report.
                Route::get('assign',                'assignForm')->name('assign.form');
                Route::post('assign',               'assign')->name('assign');
                Route::get('billing',               'billing')->name('billing');
                Route::post('{membership}/confirm', 'confirm')->name('confirm');
                Route::post('{membership}/cancel',  'cancel')->name('cancel');
                Route::post('{membership}/extend',  'extend')->name('extend');
                Route::post('{membership}/refund',  'refund')->name('refund');
            });

        /* Referral system (new — non-withdrawable wallet stream) */
        Route::controller(\App\Http\Controllers\Admin\ReferralController::class)
            ->prefix('referrals')->name('referrals.')->group(function () {
                Route::get('/',                'index')->name('index');
                Route::get('settings',         'settings')->name('settings');
                Route::post('settings',        'updateSettings')->name('settings.update');
                Route::post('{referral}/approve', 'approve')->name('approve');
                Route::post('{referral}/reject',  'reject')->name('reject');
            });

        /* 2026-06-09 — coach custom-domain manager (governance) */
        Route::controller(\App\Http\Controllers\Admin\CustomDomainController::class)
            ->prefix('custom-domains')->name('custom-domains.')->group(function () {
                Route::get('/',               'index')->name('index');
                Route::get('settings',        'settings')->name('settings');
                Route::post('settings',       'updateSettings')->name('settings.update');
                Route::get('{domain}',        'show')->whereNumber('domain')->name('show');
                Route::post('{domain}/approve', 'approve')->whereNumber('domain')->name('approve');
                Route::post('{domain}/reject',  'reject')->whereNumber('domain')->name('reject');
                Route::post('{domain}/suspend', 'suspend')->whereNumber('domain')->name('suspend');
                Route::post('{domain}/resume',  'resume')->whereNumber('domain')->name('resume');
                Route::post('{domain}/recheck', 'recheck')->whereNumber('domain')->name('recheck');
                Route::delete('{domain}',      'remove')->whereNumber('domain')->name('remove');
            });

        /* Admin notifications — same controller as web; guard differs by route group */
        Route::controller(\App\Http\Controllers\Frontend\NotificationController::class)
            ->prefix('notifications')->name('notifications.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('recent', 'recent')->name('recent');
                Route::get('unread-count', 'unreadCount')->name('unread-count');
                Route::post('{id}/read', 'markRead')->name('mark-read');
                Route::post('read-all', 'markAllRead')->name('mark-all-read');
                Route::delete('{id}', 'destroy')->name('destroy');
            });

        /* 2FA settings (enrollment + management). Throttle the mutating
         * endpoints — the confirm step also takes a TOTP code (bruteforceable),
         * and rate-limiting disable/regenerate prevents lockout-loops if a
         * session is hijacked. */
        Route::get('2fa/setup',         [TwoFactorController::class, 'showSetup'])->name('2fa.setup');
        Route::post('2fa/enable',       [TwoFactorController::class, 'enable'])
            ->middleware('throttle:10,1')->name('2fa.enable');
        Route::post('2fa/confirm',      [TwoFactorController::class, 'confirm'])
            ->middleware('throttle:5,1')->name('2fa.confirm');
        Route::post('2fa/disable',      [TwoFactorController::class, 'disable'])
            ->middleware('throttle:5,1')->name('2fa.disable');
        Route::post('2fa/regenerate',   [TwoFactorController::class, 'regenerateRecovery'])
            ->middleware('throttle:5,1')->name('2fa.regenerate');
    });
});
