<?php

use Illuminate\Support\Facades\Route;
use Modules\Consulting\Http\Controllers\ConsultingController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('consultings', ConsultingController::class)->names('consulting');
});
