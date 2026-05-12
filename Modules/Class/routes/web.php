<?php

use Illuminate\Support\Facades\Route;
use Modules\Class\Http\Controllers\ClassController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('classes', ClassController::class)->names('class');
});
