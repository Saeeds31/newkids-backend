<?php

use Illuminate\Support\Facades\Route;
use Modules\Activity\Http\Controllers\ActivityController;
use Modules\Task\Http\Controllers\TaskController;
use Modules\Users\Http\Controllers\TeacherController;

Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('teachers', TeacherController::class)->names('users');
});
Route::middleware(['auth:sanctum'])->prefix('v1/teacher')->group(function () {
    Route::get('/students', [TeacherController::class, 'getStudents'])->name('teachers-getStudents');
    Route::get('/dashboard-task', [TaskController::class, 'getTeacherDashboardTasks'])->name('teachers-getTeacherDashboardTasks');
    Route::post('/complete-task', [TaskController::class, 'completeTask'])->name('teachers-completeTask');
    Route::get('/classes', [TeacherController::class, 'getClasses'])->name('teachers-getClasses');
    Route::get('/tasks', [TeacherController::class, 'getTasks'])->name('teachers-getTasks');
    Route::get('/activities', [ActivityController::class, 'getUserActivities'])->name('teachers-getTasks');
});
