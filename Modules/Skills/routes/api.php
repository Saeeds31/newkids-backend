<?php

use Illuminate\Support\Facades\Route;
use Modules\Skills\Http\Controllers\SkillsController;

Route::middleware(['auth:sanctum'])->prefix('v1/manager')->group(function () {
    Route::apiResource('skills', SkillsController::class);
    Route::get('skills/colors/palette', [SkillsController::class, 'getColorPalette']);
});
