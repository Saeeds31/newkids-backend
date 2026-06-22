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
        Schema::create('behavioral_information', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_time');
            $table->string('tv_time');
            $table->string('shy');
            $table->string('sensitivity');
            $table->string('aggression');
            $table->string('fear');
            $table->string('anxiety');
            $table->string('Dependence_parent');
            $table->string('find_firends');
            $table->string('express_fear');
            $table->string('express_anger');
            $table->string('React_not');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('behavioral_information');
    }
};
