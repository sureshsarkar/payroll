<?php

use Illuminate\Support\Facades\Route;
use Modules\BasicPayment\app\Http\Controllers\API\PaymentController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('payment-gateway-list', [PaymentController::class, 'all_payment']);
    Route::get('payment-api/{method}', [PaymentController::class, 'placeOrder']);
    Route::get('free-order', [PaymentController::class, 'pay_via_free_gateway']);
});
