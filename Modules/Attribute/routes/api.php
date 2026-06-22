<?php

use Illuminate\Support\Facades\Route;
use Modules\Attribute\Http\Controllers\AttributeController;

Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('attributes', AttributeController::class)->names('attribute');
});
