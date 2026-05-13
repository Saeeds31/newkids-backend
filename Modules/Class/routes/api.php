<?php

use Illuminate\Support\Facades\Route;
use Modules\Class\Http\Controllers\ClassController;

Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('classes', ClassController::class)->names('class');
    // Route::prefix('classes')->group(function () {
    //     Route::get('/trashed', [ClassController::class, 'trashed'])->name('classes.trashed');
    //     Route::post('/{id}/restore', [ClassController::class, 'restore'])->name('classes.restore');
    //     Route::delete('/{id}/force', [ClassController::class, 'forceDelete'])->name('classes.force-delete');
    // });
});
