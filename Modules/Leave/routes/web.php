<?php

use Illuminate\Support\Facades\Route;
use Modules\Leave\app\Http\Controllers\LeaveApprovalController;
use Modules\Leave\app\Http\Controllers\LeaveController;

/*
|--------------------------------------------------------------------------
| Leave module — web routes
|--------------------------------------------------------------------------
| Employee (studentrole) : balances, apply, cancel
| HR (instructorrole)    : approve / reject team leave
*/

// ---- Employee ------------------------------------------------------------
Route::middleware(['web', 'auth', 'studentrole'])
    ->prefix('employee/leave')
    ->name('employee.leave.')
    ->group(function () {
        Route::get('/', [LeaveController::class, 'index'])->name('index');
        Route::post('/', [LeaveController::class, 'store'])->name('store');
        Route::post('{leave}/cancel', [LeaveController::class, 'cancel'])->name('cancel');
    });

// ---- HR ------------------------------------------------------------------
Route::middleware(['web', 'auth', 'instructorrole'])
    ->prefix('hr/leave')
    ->name('hr.leave.')
    ->group(function () {
        Route::get('/', [LeaveApprovalController::class, 'index'])->name('index');
        Route::post('{leave}/approve', [LeaveApprovalController::class, 'approve'])->name('approve');
        Route::post('{leave}/reject', [LeaveApprovalController::class, 'reject'])->name('reject');
    });
