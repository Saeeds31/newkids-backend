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
        Schema::create('task_result_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_result_id')->constrained()->onDelete('cascade');
            $table->foreignId('evaluation_criterion_id')->constrained('task_evaluation_criteria')->onDelete('cascade');
            $table->integer('score')->nullable();
            $table->timestamps();
            $table->unique(['task_result_id', 'evaluation_criterion_id'], 'result_criterion_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_result_evaluations');
    }
};
