<?php

use Illuminate\Support\Facades\Route;
use Modules\Skills\Http\Controllers\SkillsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('skills', SkillsController::class)->names('skills');
});
