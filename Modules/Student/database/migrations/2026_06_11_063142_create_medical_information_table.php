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
        Schema::create('medical_information', function (Blueprint $table) {
            $table->id();
            $table->integer("height");
            $table->integer("weight");
            $table->integer("blood_type");
            $table->string("special_disease")->nullable();
            $table->string("food_allergy")->nullable();
            $table->string("drug_allergy")->nullable();
            $table->string("skin_sensitivity")->nullable();
            $table->string("sleep_time")->nullable();
            $table->string("sleep_quality")->nullable();
            $table->string("favorite_food")->nullable();
            $table->string("unfavorite_food")->nullable();
            $table->string("doctor_name")->nullable();
            $table->string("doctor_phone")->nullable();
            $table->string("emergency_phone")->nullable();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_information');
    }
};
