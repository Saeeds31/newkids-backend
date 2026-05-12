<?php

use Illuminate\Support\Facades\Route;
use Modules\Class\Http\Controllers\ClassController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('classes', ClassController::class)->names('class');
});
