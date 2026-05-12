<?php

use Illuminate\Support\Facades\Route;
use Modules\Grade\Http\Controllers\GradeController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('grades', GradeController::class)->names('grade');
});
