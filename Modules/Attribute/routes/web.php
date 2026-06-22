<?php

use Illuminate\Support\Facades\Route;
use Modules\Attribute\Http\Controllers\AttributeController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('attributes', AttributeController::class)->names('attribute');
});
