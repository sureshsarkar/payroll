<?php

use Illuminate\Support\Facades\Route;
use Modules\HrEmployee\app\Http\Controllers\HrEmployeeController;

/*
|--------------------------------------------------------------------------
| HrEmployee module — web routes
|--------------------------------------------------------------------------
| HR (instructorrole): employee onboarding + department management.
*/

Route::middleware(['web', 'auth', 'instructorrole'])
    ->prefix('hr')
    ->name('hr.')
    ->group(function () {
        Route::get('employees', [HrEmployeeController::class, 'index'])->name('employees.index');
        Route::post('employees/profile', [HrEmployeeController::class, 'storeProfile'])->name('employees.profile');

        Route::get('departments', [HrEmployeeController::class, 'departments'])->name('departments.index');
        Route::post('departments', [HrEmployeeController::class, 'storeDepartment'])->name('departments.store');
    });
