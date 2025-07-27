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
        Schema::create('onboarding_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('event_type', 50)->comment('イベント種別');
            $table->unsignedTinyInteger('step_number')->nullable()->comment('ステップ番号');
            $table->json('data')->nullable()->comment('追加データ');
            $table->string('session_id', 100)->nullable()->comment('セッションID');
            $table->string('user_agent', 500)->nullable()->comment('ユーザーエージェント');
            $table->ipAddress('ip_address')->nullable()->comment('IPアドレス');
            $table->timestamp('created_at')->useCurrent()->comment('作成日時');

            // インデックス
            $table->index(['user_id', 'event_type'], 'idx_onboarding_logs_user_event');
            $table->index('created_at', 'idx_onboarding_logs_created_at');
            $table->index(['event_type', 'created_at'], 'idx_onboarding_logs_event_created');
            $table->index('session_id', 'idx_onboarding_logs_session');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_logs');
    }
};
