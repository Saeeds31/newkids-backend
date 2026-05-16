<?php

use Illuminate\Support\Facades\Route;
use Modules\Class\Http\Controllers\ClassController;
use Modules\Class\Http\Controllers\ClassSubjectTimeController;

Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('classes', ClassController::class)->names('class');
});
// routes/api.php
Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('classes-time', ClassSubjectTimeController::class)->names('class');
});
Route::middleware(['auth:sanctum'])->prefix('v1/manager/classes-time/')->group(function () {
    Route::get('/class/{classId}', [ClassSubjectTimeController::class, 'getClassSchedule']);
    Route::get('/subject/{subjectId}', [ClassSubjectTimeController::class, 'getSubjectSchedule']);
    Route::get('/class/{classId}/free-times/{dayOfWeek}', [ClassSubjectTimeController::class, 'getFreeTimes']);
});
Route::middleware(['auth:sanctum'])->prefix('v1/teacher')->group(function () {
    Route::get('/{teacherId}', [ClassSubjectTimeController::class, 'getTeacherSchedule']);
});
