<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // کی انجام داده
            $table->string('model'); // اسم مدل (مثلاً Student, Task)
            $table->unsignedBigInteger('model_id'); // ایدی اون مدل
            $table->string('action'); // عملیات (create, update, delete)
            $table->text('description')->nullable(); // توضیحات
            $table->timestamps();
            // ایندکس برای جستجوی بهتر
            $table->index(['model', 'model_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
