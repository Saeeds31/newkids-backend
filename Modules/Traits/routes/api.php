<?php

use Illuminate\Support\Facades\Route;
use Modules\Traits\Http\Controllers\TraitsController;

Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('traits', TraitsController::class);
    Route::get('traits/colors/palette', [TraitsController::class, 'getColorPalette']);
});
