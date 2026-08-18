<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\app\Http\Controllers\DashboardController;
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

// ---- Employee: dashboard + payslips ---------------------------------------
Route::middleware(['web', 'auth', 'studentrole', 'companycontext'])->group(function () {
    Route::get('employee/overview', [DashboardController::class, 'employee'])->name('employee.overview');

    Route::prefix('employee/payslips')->name('employee.payslips.')->group(function () {
        Route::get('/', [PayslipController::class, 'index'])->name('index');
        Route::get('{item}/download', [PayslipController::class, 'download'])->name('download');
        Route::get('{item}/form-xi', [PayslipController::class, 'formXi'])->name('formxi');
    });
});

// ---- HR: salary structures + payroll runs ---------------------------------
Route::middleware(['web', 'auth', 'instructorrole', 'companycontext'])
    ->prefix('hr')
    ->name('hr.')
    ->group(function () {
        Route::get('overview', [DashboardController::class, 'hr'])->name('overview');

        Route::get('salary-structures', [SalaryStructureController::class, 'index'])->name('salary.index');
        Route::post('salary-structures', [SalaryStructureController::class, 'store'])->name('salary.store');

        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('payroll/prepare', [PayrollController::class, 'prepare'])->name('payroll.prepare');
        Route::get('payroll/{run}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::get('payroll/{run}/export', [PayrollController::class, 'exportRun'])->name('payroll.export');
        Route::get('payroll/{run}/slip/{employee}', [PayrollController::class, 'exportEmployee'])->name('payroll.slip');
        Route::get('payroll/{run}/payslip/{employee}', [PayrollController::class, 'exportPayslip'])->name('payroll.payslip');
        Route::post('payroll/{run}/submit', [PayrollController::class, 'submit'])->name('payroll.submit');
    });

// ---- Super Admin: approval ------------------------------------------------
Route::middleware(['web', 'auth:admin'])
    ->prefix('admin/payroll')
    ->name('admin.payroll.')
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'admin'])->name('dashboard');
        Route::get('/', [PayrollController::class, 'adminIndex'])->name('index');
        Route::get('{run}', [PayrollController::class, 'adminShow'])->name('show');
        Route::get('{run}/export', [PayrollController::class, 'exportRun'])->name('export');
        Route::get('{run}/slip/{employee}', [PayrollController::class, 'exportEmployee'])->name('slip');
        Route::get('{run}/payslip/{employee}', [PayrollController::class, 'exportPayslip'])->name('payslip');
        Route::post('{run}/approve', [PayrollController::class, 'approve'])->name('approve');
    });
