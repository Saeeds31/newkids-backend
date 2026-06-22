<?php

use Illuminate\Support\Facades\Route;
use Modules\Student\Http\Controllers\StudentController;

Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    // مسیرهای دانش‌آموزان
    Route::apiResource('students', StudentController::class);
    // مسیرهای اضافی
    Route::post('student/info', [StudentController::class, 'saveInfo'])->name('saveInfo');
    Route::post('student/medical-information', [StudentController::class, 'saveMedicalInformation'])->name('saveMedicalInformation');
    Route::post('student/drug', [StudentController::class, 'storeDrug'])->name('storeDrug');
    Route::put('student/drug/{id}', [StudentController::class, 'updateDrug'])->name('updateDrug');
    Route::delete('student/drug/{id}', [StudentController::class, 'destroyDrug'])->name('destroyDrug');
    
    Route::prefix('students')->group(function () {
        Route::get('/by-class/{classId}', [StudentController::class, 'getStudentsByClass'])->name('students.by-class');
        Route::get('/search', [StudentController::class, 'search'])->name('students.search');
    });
});

