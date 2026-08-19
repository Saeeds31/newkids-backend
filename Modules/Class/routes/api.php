<?php

use Illuminate\Support\Facades\Route;
use Modules\Class\Http\Controllers\ClassController;
use Modules\Class\Http\Controllers\ClassSubjectTimeController;
use Modules\Class\Http\Controllers\TeacherClassController;

Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('classes', ClassController::class)->names('class');
});
// routes/api.php
// در routes/api.php یا routes/manager.php
Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('classes-time', ClassSubjectTimeController::class)->names('class');

    Route::get('/classes-time-form-data', [ClassSubjectTimeController::class, 'getFormData']);
    Route::prefix('classes-time')->group(function () {
        Route::get('/class/{classId}', [ClassSubjectTimeController::class, 'getClassSchedule']);
        Route::get('/subject/{subjectId}', [ClassSubjectTimeController::class, 'getSubjectSchedule']);
        Route::get('/class/{classId}/free-times/{dayOfWeek}', [ClassSubjectTimeController::class, 'getFreeTimes']);
    });
});
Route::middleware(['auth:sanctum'])->prefix('v1/teacher')->group(function () {
    Route::get('/{teacherId}', [ClassSubjectTimeController::class, 'getTeacherSchedule']);
});
Route::middleware(['auth:sanctum'])->prefix('v1/teacher-classes')->group(function () {
    // کلاس‌ها
        Route::get('/', [TeacherClassController::class, 'index'])->name('TeacherClassControllerindex');
        Route::get('/today', [TeacherClassController::class, 'getTodayClasses'])->name('TeacherClassControllergetTodayClasses');
        Route::get('/weekly', [TeacherClassController::class, 'getWeeklySchedule'])->name('TeacherClassControllergetWeeklySchedule');
        Route::get('/{id}', [TeacherClassController::class, 'show'])->name('TeacherClassControllershow');
        Route::get('/{classId}/students', [TeacherClassController::class, 'getStudents'])->name('TeacherClassControllergetStudents');
});
