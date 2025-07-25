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
        Schema::create('pomodoro_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('study_session_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('session_type', ['focus', 'short_break', 'long_break']);
            $table->integer('planned_duration'); // 計画された時間（分）
            $table->integer('actual_duration')->nullable(); // 実際の時間（分）
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->boolean('was_interrupted')->default(false);
            $table->json('settings')->nullable(); // フォーカス時間、休憩時間の設定を保存
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['session_type', 'is_completed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pomodoro_sessions');
    }
};
