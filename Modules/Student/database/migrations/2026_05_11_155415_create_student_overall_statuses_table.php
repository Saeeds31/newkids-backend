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
        Schema::create('student_overall_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->integer('term'); // 1 یا 2
            $table->integer('academic_year'); // 1404
            $table->decimal('total_score_percentage', 5, 2); // درصد نهایی
            $table->string('status_label'); // Excellent, Good, Average, Needs Improvement, Poor
            $table->json('category_breakdown'); // ذخیره نمره هر دسته (مثلاً individual, social, cognitive, behavioral)
            $table->json('trait_scores')->nullable(); // نمره هر ویژگی به صورت جداگانه
            $table->json('skill_scores')->nullable();  // نمره هر مهارت به صورت جداگانه
            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamps();
            $table->unique(['student_id', 'term', 'academic_year'], 'student_term_year_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_overall_statuses');
    }
};
