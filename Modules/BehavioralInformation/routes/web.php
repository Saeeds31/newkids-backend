<?php

use Illuminate\Support\Facades\Route;
use Modules\BehavioralInformation\Http\Controllers\BehavioralInformationController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('behavioralinformations', BehavioralInformationController::class)->names('behavioralinformation');
});
