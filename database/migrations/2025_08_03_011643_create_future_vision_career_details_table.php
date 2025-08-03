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
        Schema::create('future_vision_career_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('future_vision_id')->unique()->constrained()->onDelete('cascade');
            $table->string('position_upgrade', 100)->nullable()->comment('昇進・昇格');
            $table->text('company_opportunities')->nullable()->comment('転職・面接機会');
            $table->string('skill_improvement_areas', 500)->nullable()->comment('スキル向上領域');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('future_vision_career_details');
    }
};
