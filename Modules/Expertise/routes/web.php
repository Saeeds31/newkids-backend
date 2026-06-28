<?php

use Illuminate\Support\Facades\Route;
use Modules\Expertise\Http\Controllers\ExpertiseController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('expertises', ExpertiseController::class)->names('expertise');
});
