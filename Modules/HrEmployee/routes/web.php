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
        Route::get('employees/create', [HrEmployeeController::class, 'create'])->name('employees.create');
        Route::post('employees', [HrEmployeeController::class, 'storeEmployee'])->name('employees.store');
        Route::get('employees/{employee}/edit', [HrEmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('employees/{employee}', [HrEmployeeController::class, 'updateEmployee'])->name('employees.update');
        Route::post('employees/profile', [HrEmployeeController::class, 'storeProfile'])->name('employees.profile');

        Route::get('departments', [HrEmployeeController::class, 'departments'])->name('departments.index');
        Route::post('departments', [HrEmployeeController::class, 'storeDepartment'])->name('departments.store');
    });
