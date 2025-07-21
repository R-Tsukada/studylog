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
        Schema::create('study_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ユーザーID');
            $table->foreignId('exam_type_id')->constrained()->comment('試験タイプID');
            $table->integer('daily_minutes_goal')->nullable()->comment('日別目標学習時間（分）');
            $table->integer('weekly_minutes_goal')->nullable()->comment('週間目標学習時間（分）');
            $table->date('exam_date')->nullable()->comment('試験日');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->timestamps();

            $table->index(['user_id', 'is_active'], 'idx_user_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_goals');
    }
};
