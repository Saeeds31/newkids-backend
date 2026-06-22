<?php

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\AuthController;
use Modules\Users\Http\Controllers\RolesController;
use Modules\Users\Http\Controllers\UsersController;

Route::prefix('v1/public')->group(function () {
    Route::post('/check-login', [AuthController::class, 'CheckLogin'])->name("CheckLogin");
    Route::post('/login-password', [AuthController::class, 'loginWithPassword']);
    Route::post('/send-otp', [AuthController::class, 'publicSendToken']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::get('/logout', [AuthController::class, 'logoutUserFront']);
});

// 
Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    Route::apiResource('users', UsersController::class)->names('users');
    Route::get('/supporter', [UsersController::class, 'getSupporter'])->name("getSupporter");
    Route::apiResource('roles', RolesController::class)->names('roles');
    Route::get('/admin-info', [UsersController::class, 'adminInfo'])->name("adminInfo");
    Route::get('/user-managers', [UsersController::class, 'managerIndex'])->name("managerIndex");
    Route::post('/user-managers/assign-roles', [RolesController::class, 'assignRoles'])->name("assignRoles");
    Route::get('/all-permissions', [RolesController::class, 'allPermissions'])->name("allPermissions");
    Route::post('/save-permissions', [RolesController::class, 'savePermissions'])->name("savePermissions");
});
Route::post('v1/admin/login-verify', [AuthController::class, 'adminLogin'])->name("adminLogin");
Route::post('v1/admin/send-token', [AuthController::class, 'adminSendToken'])->name("adminSendToken");
Route::post('v1/employer/login', [AuthController::class, 'employerLogin'])->name("employerLogin");
Route::post('v1/employer/send-token', [AuthController::class, 'adminSendToken'])->name("adminSendToken");


Route::middleware(['auth:sanctum'])->prefix('v1/front')->group(function () {
    Route::get('/user/profile', [UsersController::class, 'userProfile']);
    Route::put('/user/profile', [UsersController::class, 'updateProfile']);
});

Route::prefix('v1/')->middleware(['auth:sanctum'])->group(function () {
    require_once __DIR__ . '/teacher.php';
});
Route::prefix('v1/')->middleware(['auth:sanctum'])->group(function () {
    require_once __DIR__ . '/parent.php';
});