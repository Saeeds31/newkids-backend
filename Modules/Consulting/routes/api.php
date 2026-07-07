<?php

use Illuminate\Support\Facades\Route;
use Modules\Consulting\Http\Controllers\ConsultingController;

Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    Route::get('/consultings', [ConsultingController::class, 'adminIndex']);
    Route::get('/consultings/{id}', [ConsultingController::class, 'adminShow']);
    Route::put('/consultings/{id}/status', [ConsultingController::class, 'adminUpdateStatus']);
});
Route::post('/consultings', [ConsultingController::class, 'store']);
