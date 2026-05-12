<?php

use Illuminate\Support\Facades\Route;
use Modules\Grade\Http\Controllers\GradeController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('grades', GradeController::class)->names('grade');
});
