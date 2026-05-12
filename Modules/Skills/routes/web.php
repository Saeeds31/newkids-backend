<?php

use Illuminate\Support\Facades\Route;
use Modules\Skills\Http\Controllers\SkillsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('skills', SkillsController::class)->names('skills');
});
