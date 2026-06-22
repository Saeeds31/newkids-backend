<?php

use Illuminate\Support\Facades\Route;
use Modules\BehavioralInformation\Http\Controllers\BehavioralInformationController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('behavioralinformations', BehavioralInformationController::class)->names('behavioralinformation');
});
