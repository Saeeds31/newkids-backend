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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('labels')->nullable();
            $table->string('color_code')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['todo','doing', 'done', 'closed'])->default('todo');
            $table->foreignId('created_by')->constrained('users');
            $table->enum('type', ['routine', 'once']);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
