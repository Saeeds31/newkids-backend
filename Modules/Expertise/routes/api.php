<?php

use Illuminate\Support\Facades\Route;
use Modules\Expertise\Http\Controllers\ExpertiseController;

Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('expertises', ExpertiseController::class)->names('expertise');
});
