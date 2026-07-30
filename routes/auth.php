<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:web')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    // 2026-07-07 (QA audit) — abuse protection on the platform auth POSTs, to
    // match user-login (throttle:5,1). Blocks registration spam, password-reset
    // email flooding and reset brute-force.
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:6,1');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('user-login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('user-login');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');

    Route::post('/forget-password', [PasswordResetLinkController::class, 'custom_forget_password'])
        ->middleware('throttle:6,1')
        ->name('forget-password');

    Route::get('/reset-password-page/{token}', [NewPasswordController::class, 'custom_reset_password_page'])->name('reset-password-page');

    Route::post('/reset-password-store/{token}', [NewPasswordController::class, 'custom_reset_password_store'])
        ->middleware('throttle:6,1')
        ->name('reset-password-store');

    Route::get('/user-verification/{token}', [RegisteredUserController::class, 'custom_user_verification'])->name('user-verification');

    Route::controller(SocialiteController::class)->group(function () {
        Route::get('auth/{driver}', 'redirectToDriver')->name('auth.social');
        Route::get('auth/{driver}/callback', 'handleDriverCallback')->name('auth.social.callback');
    });

});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    
    Route::get('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logoutme');
        
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
