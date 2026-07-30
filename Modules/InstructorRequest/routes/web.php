<?php

use Illuminate\Support\Facades\Route;
use Modules\InstructorRequest\app\Http\Controllers\InstructorRequestController;
use Modules\InstructorRequest\app\Http\Controllers\InstructorRequestSettingController;

Route::group(['as' => 'admin.', 'prefix' => 'admin', 'middleware' => ['auth:admin', 'translation']], function () {
    // Audit 2026-05-15: constrain to what controllers actually implement.
    // InstructorRequestController: {index, edit, update, destroy}.
    // InstructorRequestSettingController: {index, update} (singleton).
    Route::resource('instructor-request', InstructorRequestController::class)
        ->only(['index', 'edit', 'update', 'destroy'])
        ->names('instructor-request');
    Route::resource('instructor-request-setting', InstructorRequestSettingController::class)
        ->only(['index', 'update'])
        ->names('instructor-request-setting');

});
