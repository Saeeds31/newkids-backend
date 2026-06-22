<?php

use Illuminate\Support\Facades\Route;
use Modules\Concern\Http\Controllers\ConcernController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('concerns', ConcernController::class)->names('concern');
});
