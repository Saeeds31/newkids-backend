<?php

use Illuminate\Support\Facades\Route;
use Modules\Task\Http\Controllers\TaskController;

// routes/api.php
Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('tasks', TaskController::class);
    Route::get('/tasks/color-palette', [TaskController::class, 'getColorPalette'])->name('tasks.getColorPalette');
});
Route::prefix('tasks')->controller(TaskController::class)->group(function () {
    Route::post('/results', 'storeResult')->name('tasks.results.store');
    Route::get('/{taskId}/results', 'getTaskResults')->name('tasks.results.index');
    Route::get('/{taskId}/statistics', 'getTaskStatistics')->name('tasks.statistics');
});
