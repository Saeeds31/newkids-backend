<?php

use Illuminate\Support\Facades\Route;
use Modules\Task\Http\Controllers\TaskController;

// routes/api.php
Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('tasks', TaskController::class);
    Route::get('/tasks/color-palette', [TaskController::class, 'getColorPalette'])->name('tasks.getColorPalette');
});
