<?php

use Illuminate\Support\Facades\Route;
use Modules\CryptoPayment\app\Http\Controllers\CoinGateController;
use Modules\CryptoPayment\app\Http\Controllers\CryptoPaymentController;

Route::group(['as' => 'admin.', 'prefix' => 'admin', 'middleware' => ['translation']], function () {
    Route::resource('cryptopayment', CryptoPaymentController::class)->names('cryptopayment')->only('update');
});


Route::middleware('auth:web')->group(function () {
    Route::get('/crypto/payment', [CoinGateController::class, 'createPayment'])->name('pay.coingate');
    Route::get('/coin-gate/callback/{?token}',  [CoinGateController::class, 'handleCallback']);
    Route::get('/coin-gate/success', [CoinGateController::class, 'success']);
});