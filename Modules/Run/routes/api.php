<?php

use Illuminate\Support\Facades\Route;
use Modules\Run\Http\Controllers\RunController;
// استارت پروژه با اجرای این مسیرهاست
Route::prefix('v1/admin')->group(function () {
    Route::get('run/run', [RunController::class, "runShop"])->name('runShop');
    Route::get('run/permissions', [RunController::class, 'setPermissions'])->name('setPermissions');
    Route::get('run/assign', [RunController::class, 'setSuperAdminPermissions'])->name('setSuperAdminPermissions');
});
Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    // اختصاص پرمیژن ها از پنل ادمین
    Route::get('run/permissions/manager', [RunController::class, 'setManagerPermissions'])->name('setPermissions');
    Route::get('run/permissions/teacher', [RunController::class, 'setTeacherPermissions'])->name('setPermissions');
    Route::get('run/permissions/parent', [RunController::class, 'setParentPermissions'])->name('setPermissions');
});
