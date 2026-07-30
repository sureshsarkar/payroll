<?php

use Illuminate\Support\Facades\Route;
use Modules\BkashPG\app\Http\Controllers\BkashPGController;
use Modules\BkashPG\app\Http\Controllers\BkashTokenizePaymentController;

Route::group(['middleware' => ['web', 'auth:admin', 'translation'], 'prefix' => 'admin', 'as' => 'admin.'], function () {
    Route::resource('bkash', BkashPGController::class)->names('bkash')->only('update');
});

Route::group(['middleware' => ['web','auth', 'verified']], function () {
    Route::get('bkash/payment', [BkashTokenizePaymentController::class, 'onCreate'])->name('pay.bkash');
    Route::get('bkash/callback/', [BkashTokenizePaymentController::class, 'onSuccess'])->name('pay.bkash.success');
});
