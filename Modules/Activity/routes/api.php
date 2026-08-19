<?php

use Illuminate\Support\Facades\Route;
use Modules\Activity\Http\Controllers\ActivityController;
use Modules\Activity\Http\Controllers\DashboardController;
use Modules\Activity\Http\Controllers\TeacherDashboardController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('activities', ActivityController::class)->names('activity');
});
Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'managerDashboard'])->name('managerDashboard');
});
Route::middleware(['auth:sanctum'])->prefix('v1/manager-activities')->group(function () {
    // مسیرهای Activity
    Route::get('/', [ActivityController::class, 'index']);
    Route::get('/user', [ActivityController::class, 'getUserActivities']);
    Route::get('/model/{model}/{modelId}', [ActivityController::class, 'getModelActivities']);
});
// در routes/api.php یا routes/teacher.php
Route::middleware(['auth:sanctum'])->prefix('v1/teacher')->group(function () {
    // داشبورد معلم
    Route::get('/dashboard', [TeacherDashboardController::class, 'index'])->name('TeacherDashboardControllerindex');
});
