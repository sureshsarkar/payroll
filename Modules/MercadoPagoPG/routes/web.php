<?php

use Illuminate\Support\Facades\Route;
use Modules\MercadoPagoPG\app\Http\Controllers\MercadoPagoPGController;
use Modules\MercadoPagoPG\app\Http\Controllers\PaymentController;

Route::group(['middleware' => ['web', 'auth:admin', 'translation'], 'prefix' => 'admin', 'as' => 'admin.'], function () {
    Route::resource('mercadopagopg', MercadoPagoPGController::class)->names('mercadopagopg')->only('update');
});



Route::group(['middleware' => ['web', 'auth', 'verified']], function () {
    Route::post('create-preference', [PaymentController::class, 'createPreference'])->name('pay.mercadopago.preference');
    Route::get('mercadopago/payment', [PaymentController::class, 'createPayment'])->name('pay.mercadopago');

    Route::get('mercadopago/callback/', [PaymentController::class, 'onSuccess'])->name('pay.mercadopago.success');
});

Route::get('mercadopago/success', [PaymentController::class, 'success'])->name('mercadopago.success');
Route::get('mercadopago/failed', [PaymentController::class, 'failed'])->name('mercadopago.failed');
