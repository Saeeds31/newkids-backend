<?php

use Illuminate\Support\Facades\Route;
use Modules\Consulting\Http\Controllers\ConsultingController;

Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    Route::get('/consultings', [ConsultingController::class, 'adminIndex']);
    Route::put('/consultings/{id}/status', [ConsultingController::class, 'adminUpdateStatus']);
    Route::get('/consultings/{id}', [ConsultingController::class, 'adminShow']);
});
Route::post('/consultings', [ConsultingController::class, 'store']);
