<?php

use Illuminate\Support\Facades\Route;
use Modules\Student\Http\Controllers\StudentController;

Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    // مسیرهای دانش‌آموزان
    Route::apiResource('students', StudentController::class);
    // مسیرهای اضافی
    Route::prefix('students')->group(function () {
        Route::get('/by-class/{classId}', [StudentController::class, 'getStudentsByClass'])->name('students.by-class');
        Route::get('/search', [StudentController::class, 'search'])->name('students.search');
    });
});
