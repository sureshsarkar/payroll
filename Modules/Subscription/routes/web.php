<?php

use Illuminate\Support\Facades\Route;
use Modules\Subscription\app\Http\Controllers\SubscriptionController;

Route::group(['as' => 'admin.', 'prefix' => 'admin', 'middleware' => ['auth:admin', 'translation']], function () {

    Route::controller(SubscriptionController::class)->group(function () {
        Route::get('/subscriptions', 'index')->name('subscriptions');
        Route::get('/subscription-histories', 'subscription_histories')->name(name: 'subscription-histories');
        Route::get('/subscriptions/create', 'create')->name('subscriptions.create'); 
        Route::get('/subscriptions/{id}/edit', 'edit')->name('subscriptions.edit');
        Route::get('/subscriptions/{id}', 'show')->name('subscriptions.show');
        Route::get('/subscription-histories/{id}', 'subscription_histories_show')->name('subscription-histories.show');
        Route::post('/subscriptions/store', 'store')->name('subscriptions.store'); 
        Route::post('/subscriptions/{id}', 'update')->name('subscriptions.update');

        // NOTE: order.update, order.destroy, print-invoice, download.payment-receipt,
        // resend.gift-claim-mail and gift-course-verification used to be duplicated here.
        // They live (and the actual handlers exist) in Modules/Order/routes/web.php
        // owned by OrderController. The duplicates here pointed to SubscriptionController
        // methods that never existed → BadMethodCallException when admins clicked the
        // order-status update form. Removed so the Order module's routes win cleanly.
    });
});
