<?php

use Illuminate\Support\Facades\Route;
use Modules\Traits\Http\Controllers\TraitsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('traits', TraitsController::class)->names('traits');
});
