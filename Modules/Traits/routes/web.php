<?php

use Illuminate\Support\Facades\Route;
use Modules\Traits\Http\Controllers\TraitsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('traits', TraitsController::class)->names('traits');
});
