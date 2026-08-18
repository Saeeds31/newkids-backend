<?php

use Illuminate\Support\Facades\Route;
use Modules\Task\Http\Controllers\TaskController;


Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('tasks', TaskController::class);
    Route::get('/tasks/color-palette', [TaskController::class, 'getColorPalette'])->name('tasks.getColorPalette');
    Route::prefix('tasks-api')->group(function () {
        Route::get('/criteria-options', [TaskController::class, 'getCriteriaOptions']);
        Route::get('/assignment-options', [TaskController::class, 'getAssignmentOptions']);
        Route::get('/by-status', [TaskController::class, 'getTasksByStatus']);
        Route::get('/summary', [TaskController::class, 'getTasksSummary']);
        Route::get('/class/{classId}', [TaskController::class, 'getTasksForClass']);
        Route::get('/teacher/{teacherId}', [TaskController::class, 'getTasksForTeacher']);
        Route::get('/{taskId}/results', [TaskController::class, 'getTaskResults']);
        Route::get('/{taskId}/statistics', [TaskController::class, 'getTaskStatistics']);
        Route::post('/{taskId}/complete', [TaskController::class, 'completeTask']);
        Route::post('/result', [TaskController::class, 'storeResult']);
    });
});
