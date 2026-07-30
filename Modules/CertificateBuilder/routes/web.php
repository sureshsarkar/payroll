<?php

use Illuminate\Support\Facades\Route;
use Modules\CertificateBuilder\app\Http\Controllers\CertificateBuilderController;

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

Route::group(['middleware' => ['auth:admin', 'translation'], 'prefix' => 'admin', 'as' => 'admin.'], function () {
    Route::post('certificate-builder/item/update', [CertificateBuilderController::class, 'updateItem'])->name('certificate-builder.item.update');
    // Audit 2026-05-15: controller has {create, index, update} but the
    // 'create' view file does not exist. The certificate builder is a
    // singleton tool — index renders the editor, updateItem is the live save.
    // Drop the /create + /store + others until the corresponding views land.
    Route::resource('certificate-builder', CertificateBuilderController::class)
        ->only(['index', 'update'])
        ->names('certificate-builder');
});
