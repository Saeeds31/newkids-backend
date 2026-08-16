<?php

use Illuminate\Support\Facades\Route;
use Modules\Activity\Http\Controllers\ActivityController;
use Modules\Activity\Http\Controllers\DashboardController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('activities', ActivityController::class)->names('activity');
});
Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::get('dashboard', [DashboardController::class,'managerDashboard'])->name('managerDashboard');
});
