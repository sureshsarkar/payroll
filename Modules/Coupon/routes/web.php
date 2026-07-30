<?php

use Illuminate\Support\Facades\Route;
use Modules\Coupon\app\Http\Controllers\CouponController;

Route::group(['as' => 'admin.', 'prefix' => 'admin', 'middleware' => ['auth:admin', 'translation']], function () {

    // Audit 2026-05-15: CouponController has only {index, store, update, destroy}.
    // The /create and /edit URLs 500'd because the controller doesn't render
    // those forms (coupons are created/edited in modals on the index page).
    Route::resource('coupon', CouponController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('coupon');

    // Audit 2026-05-15: coupon_history() method does not exist on
    // CouponController. Route was vestigial — removed.
    // If you need a usage history view, add the method and re-register.
    // Route::get('coupon-history', [CouponController::class, 'coupon_history'])->name('coupon-history');

});
