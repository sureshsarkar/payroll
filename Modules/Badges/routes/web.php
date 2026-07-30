<?php

use Illuminate\Support\Facades\Route;
use Modules\Badges\app\Http\Controllers\BadgesController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['middleware' => ['auth:admin', 'translation'], 'prefix' => 'admin', 'as' => 'admin.'], function () {
    Route::put('registration-badge', [BadgesController::class, 'registrationBadge'])->name('registration-badge');
    // Audit 2026-05-15: BadgesController is index-only (registration badge
    // settings are a singleton row edited in place via the index page).
    // Previous Route::resource() exposed /create, /store, /edit, /update,
    // /destroy URLs that 500'd. Limit to what the controller actually has.
    Route::resource('badges', BadgesController::class)->only(['index'])->names('badges');
});
