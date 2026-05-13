<?php

use Illuminate\Support\Facades\Route;
use Modules\Grade\Http\Controllers\GradeController;

Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('grades', GradeController::class)->names('grade');
    Route::prefix('grades')->group(function () {
        Route::get('/trashed', [GradeController::class, 'trashed'])->name('grades.trashed');
        Route::post('/{id}/restore', [GradeController::class, 'restore'])->name('grades.restore');
        Route::delete('/{id}/force', [GradeController::class, 'forceDelete'])->name('grades.force-delete');
    });
});
