<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\app\Http\Controllers\PayrollController;
use Modules\Payroll\app\Http\Controllers\PayslipController;
use Modules\Payroll\app\Http\Controllers\SalaryStructureController;

/*
|--------------------------------------------------------------------------
| Payroll module — web routes
|--------------------------------------------------------------------------
| Employee (studentrole) : own payslips
| HR (instructorrole)    : salary structures + payroll runs (prepare/submit)
| Super Admin (auth:admin): approve a submitted run
*/

// ---- Employee: payslips ---------------------------------------------------
Route::middleware(['web', 'auth', 'studentrole'])
    ->prefix('employee/payslips')
    ->name('employee.payslips.')
    ->group(function () {
        Route::get('/', [PayslipController::class, 'index'])->name('index');
        Route::get('{item}/download', [PayslipController::class, 'download'])->name('download');
    });

// ---- HR: salary structures + payroll runs ---------------------------------
Route::middleware(['web', 'auth', 'instructorrole'])
    ->prefix('hr')
    ->name('hr.')
    ->group(function () {
        Route::get('salary-structures', [SalaryStructureController::class, 'index'])->name('salary.index');
        Route::post('salary-structures', [SalaryStructureController::class, 'store'])->name('salary.store');

        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('payroll/prepare', [PayrollController::class, 'prepare'])->name('payroll.prepare');
        Route::get('payroll/{run}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::post('payroll/{run}/submit', [PayrollController::class, 'submit'])->name('payroll.submit');
    });

// ---- Super Admin: approval ------------------------------------------------
Route::middleware(['web', 'auth:admin'])
    ->prefix('admin/payroll')
    ->name('admin.payroll.')
    ->group(function () {
        Route::post('{run}/approve', [PayrollController::class, 'approve'])->name('approve');
    });
