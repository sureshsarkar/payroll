<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\app\Http\Controllers\AttendanceController;

/*
|--------------------------------------------------------------------------
| Attendance module — web routes
|--------------------------------------------------------------------------
| Employee (role=student) self-service + HR (role=instructor) team screens.
| Role gates reuse the app's existing `studentrole` / `instructorrole`
| middleware aliases (see app/Http/Kernel.php).
*/

// ---- Employee self-service ------------------------------------------------
Route::middleware(['web', 'auth', 'studentrole', 'companycontext'])
    ->prefix('employee/attendance')
    ->name('employee.attendance.')
    ->group(function () {
        Route::get('/', [AttendanceController::class, 'myAttendance'])->name('my');
        Route::get('export', [AttendanceController::class, 'exportMySheet'])->name('export');
        Route::post('check-in', [AttendanceController::class, 'checkIn'])->name('checkin');
        Route::post('check-out', [AttendanceController::class, 'checkOut'])->name('checkout');
    });

// ---- HR team management ---------------------------------------------------
Route::middleware(['web', 'auth', 'instructorrole', 'companycontext'])
    ->prefix('hr/attendance')
    ->name('hr.attendance.')
    ->group(function () {
        Route::get('team', [AttendanceController::class, 'team'])->name('team');
        Route::post('mark', [AttendanceController::class, 'bulkStore'])->name('mark');
        Route::get('sheet', [AttendanceController::class, 'teamSheet'])->name('sheet');
        Route::post('sheet/day', [AttendanceController::class, 'storeIndividualDay'])->name('sheet.day.store');
        Route::post('sheet/month/random-fill', [AttendanceController::class, 'fillMonthWithRandomTimes'])->name('sheet.month.random-fill');
        Route::get('sheet/employee/export', [AttendanceController::class, 'exportEmployeeSheet'])->name('sheet.employee.export');
        Route::get('sheet/export', [AttendanceController::class, 'exportTeamSheet'])->name('sheet.export');
    });
