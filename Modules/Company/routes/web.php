<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\app\Http\Controllers\CompanyController;

/*
|--------------------------------------------------------------------------
| Company module — web routes (HR company management)
|--------------------------------------------------------------------------
| Deliberately NOT behind the `companycontext` middleware: an HR with zero
| companies must be able to reach the register form without a redirect loop.
*/
Route::middleware(['web', 'auth', 'instructorrole'])
    ->prefix('hr')
    ->name('hr.')
    ->group(function () {
        Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::get('companies/create', [CompanyController::class, 'create'])->name('companies.create');
        Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::post('companies/{company}/switch', [CompanyController::class, 'switch'])->name('companies.switch');
    });
