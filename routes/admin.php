<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Auth\NewPasswordController;
use App\Http\Controllers\Admin\Auth\PasswordResetLinkController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\TwoFactorController;


 



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

        /* LMS removal phase 2 (2026-08-27) — removed the LMS/coach-business
         * admin surfaces that lived here: cross-coach booking enquiries, the
         * Theme Studio catalogue, per-batch course attendance, batch-attendance
         * verification settings, per-coach commissions, trial sessions and
         * course announcements. */


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

        // Settings routes
        Route::get('settings', [SettingController::class, 'settings'])->name('settings');

        /* LMS removal phase 2 (2026-08-27) — removed the Zoom OAuth health
         * dashboard, coach landing-page oversight, referral commissions,
         * membership plans + user memberships, coach free-trial settings, the
         * referral wallet system and the coach custom-domain manager. All of
         * them administered the coach/LMS business, which no longer exists. */


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
