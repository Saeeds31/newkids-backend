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
        Schema::create('info', function (Blueprint $table) {
            $table->id();
            $table->string('nickname')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->string('father_name')->nullable();
            $table->string('father_phone')->nullable();
            $table->string('father_job_name')->nullable();
            $table->string('father_education')->nullable();
            $table->string('mother_education')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mother_phone')->nullable();
            $table->string('mother_job_name')->nullable();
            $table->integer('number_of_siblings')->nullable();
            $table->integer('birth_order_of_the_child')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->foreignId('student_id')->constrained()->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('info');
    }
};
