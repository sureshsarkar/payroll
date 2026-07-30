<?php

use Illuminate\Support\Facades\Route;
use Modules\SiteAppearance\app\Http\Controllers\SectionSettingController;
use Modules\SiteAppearance\app\Http\Controllers\SiteAppearanceController;
use Modules\SiteAppearance\app\Http\Controllers\SiteColorController;

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
    // Audit 2026-05-15: all three are singleton-edit settings — controllers
    // implement only {index, update}; the /create URL 500'd.
    Route::resource('site-appearance', SiteAppearanceController::class)
        ->only(['index', 'update'])
        ->names('site-appearance');
    Route::resource('section-settings', SectionSettingController::class)
        ->only(['index', 'update'])
        ->names('section-setting');
    Route::resource('site-color-settings', SiteColorController::class)
        ->only(['index', 'update'])
        ->names('site-color-setting');
});
