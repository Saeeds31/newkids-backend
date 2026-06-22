<?php

use Illuminate\Support\Facades\Route;
use Modules\Activity\Http\Controllers\ActivityController;
use Modules\Student\Http\Controllers\StudentController;
use Modules\Task\Http\Controllers\TaskController;
use Modules\Users\Http\Controllers\TeacherController;
use Modules\Users\Http\Controllers\UsersController;


Route::post('v1/register-parent', [UsersController::class, 'registerParent'])->name('parent-registerParent');

Route::middleware(['auth:sanctum'])->prefix('v1/parent')->group(function () {
    Route::post('student/pre-register', [StudentController::class, 'preRegister'])->name('registerStudent');
    
    Route::post('student/info', [StudentController::class, 'saveInfo'])->name('saveInfo');
    Route::post('student/medical-information', [StudentController::class, 'saveMedicalInformation'])->name('saveMedicalInformation');
    Route::post('student/drug', [StudentController::class, 'storeDrug'])->name('storeDrug');
    Route::put('student/drug/{id}', [StudentController::class, 'updateDrug'])->name('updateDrug');
    Route::delete('student/drug/{id}', [StudentController::class, 'destroyDrug'])->name('destroyDrug');
    
});
