<?php

use Illuminate\Support\Facades\Route;
use Modules\Interest\Http\Controllers\InterestController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('interests', InterestController::class)->names('interest');
});
