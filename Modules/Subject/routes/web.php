<?php

use Illuminate\Support\Facades\Route;
use Modules\Subject\Http\Controllers\SubjectController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('subjects', SubjectController::class)->names('subject');
});
