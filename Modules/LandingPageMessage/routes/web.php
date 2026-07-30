<?php

use Illuminate\Support\Facades\Route;
use Modules\LandingPageMessage\app\Http\Controllers\LandingPageMessageController; 

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

Route::group(['as' => 'admin.', 'prefix' => 'admin', 'middleware' => ['auth:admin', 'translation']], function () {
    Route::get('landing-page-message', [LandingPageMessageController::class, 'index'])->name('landing-page.message');
    // Audit 2026-05-15: create() returns view('landingpagemessage::create')
    // which does not exist. Landing-page messages are inbound submissions
    // from the public form (handled by the frontend, not the admin).
    // The admin reads via index/show and deletes via destroy. No /create.
    // Route::get('landing-page-message/create', [LandingPageMessageController::class, 'create'])->name('landing-page-message.create');
    // Route::post('landing-page-message/store', [LandingPageMessageController::class, 'store'])->name('landing-page-message.store');
    Route::get('landing-page-message/edit/{id}', [LandingPageMessageController::class, 'edit'])->name('landing-page-message.edit');
    Route::put('landing-page-message/update/{id}', [LandingPageMessageController::class, 'update'])->name('landing-page-message.update');
    Route::delete('landing-page-message-delete/{id}', [LandingPageMessageController::class, 'destroy'])->name('landing-page-message.delete');
    Route::get('landing-page-message/{id}', [LandingPageMessageController::class, 'show'])->name('landing-page-message.show');
});
 