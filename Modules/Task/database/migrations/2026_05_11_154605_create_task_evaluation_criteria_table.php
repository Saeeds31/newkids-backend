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
        Schema::create('task_evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->enum('criterion_type', ['trait', 'skill']);
            $table->unsignedBigInteger('criterion_id'); // id از traits یا skills
            $table->integer('weight')->nullable();
            $table->integer('max_score')->nullable();
            $table->timestamps();
            $table->unique(['task_id', 'criterion_type', 'criterion_id'], 'task_criteria_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_evaluation_criteria');
    }
};
