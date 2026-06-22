<?php

use Illuminate\Support\Facades\Route;
use Modules\Concern\Http\Controllers\ConcernController;

Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('concerns', ConcernController::class)->names('concern');
});
