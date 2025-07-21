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
        Schema::create('daily_study_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ユーザーID');
            $table->date('study_date')->comment('学習日');
            $table->integer('total_minutes')->default(0)->comment('総学習時間（分）');
            $table->integer('session_count')->default(0)->comment('学習回数');
            $table->json('subject_breakdown')->nullable()->comment('分野別学習時間');
            $table->timestamps();

            $table->unique(['user_id', 'study_date'], 'unique_user_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_study_summaries');
    }
};
