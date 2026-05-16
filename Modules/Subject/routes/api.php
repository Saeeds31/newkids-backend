<?php

use Illuminate\Support\Facades\Route;
use Modules\Subject\Http\Controllers\SubjectController;

Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('subjects', SubjectController::class)->names('subject');
});
