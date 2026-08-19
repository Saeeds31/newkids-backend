<?php

use Illuminate\Support\Facades\Route;
use Modules\Message\Http\Controllers\ChatController;
use Modules\Message\Http\Controllers\MessageController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('messages', MessageController::class)->names('message');
});
// در routes/api.php یا routes/parent.php و routes/teacher.php
Route::middleware(['auth:sanctum'])->prefix('v1/chat')->group(function () {
    // لیست مکالمات
    Route::get('/conversations', [ChatController::class, 'getConversations']);

    // پیام‌های یک مکالمه
    Route::get('/messages/{taskResultId}', [ChatController::class, 'getMessages']);

    // ارسال پیام
    Route::post('/send', [ChatController::class, 'sendMessage']);

    // تعداد پیام‌های خوانده نشده
    Route::get('/unread-count', [ChatController::class, 'getUnreadCount']);

    // علامت‌گذاری مکالمه به عنوان خوانده شده
    Route::post('/conversations/{taskResultId}/read', [ChatController::class, 'markConversationAsRead']);
});
