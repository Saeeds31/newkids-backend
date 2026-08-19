<?php

use Illuminate\Support\Facades\Route;
use Modules\Task\Http\Controllers\ParentTaskReportController;
use Modules\Task\Http\Controllers\TaskController;
use Modules\Task\Http\Controllers\TeacherTaskController;

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

Route::middleware(['auth:sanctum'])->prefix('v1/teacher-tasks')->group(function () {
    // وظایف معلم
    Route::get('/', [TeacherTaskController::class, 'index']);
    Route::get('/statistics', [TeacherTaskController::class, 'getStatistics']);
    Route::get('/classes', [TeacherTaskController::class, 'getClasses']);
    Route::get('/{taskId}/record', [TeacherTaskController::class, 'getTaskForRecording']);
    Route::post('/result', [TeacherTaskController::class, 'storeResult']); // تک دانش‌آموز
    Route::post('/results/bulk', [TeacherTaskController::class, 'storeBulkResults']);
});

Route::middleware(['auth:sanctum'])->prefix('v1/parent-reports')->group(function () {
    // گزارش‌های ثبت شده
    Route::get('/', [ParentTaskReportController::class, 'index']);
    Route::get('/children', [ParentTaskReportController::class, 'getChildren']);
    Route::get('/{id}', [ParentTaskReportController::class, 'show']);
    Route::get('/child/{childId}/tasks', [ParentTaskReportController::class, 'getTasksForChild']);
});
