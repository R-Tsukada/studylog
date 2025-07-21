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
        Schema::create('study_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ユーザーID');
            $table->foreignId('subject_area_id')->constrained()->comment('学習分野ID');
            $table->timestamp('started_at')->comment('学習開始時刻');
            $table->timestamp('ended_at')->nullable()->comment('学習終了時刻');
            $table->integer('duration_minutes')->default(0)->comment('学習時間（分）');
            $table->text('study_comment')->comment('学習コメント（必須）');
            $table->timestamps();

            $table->index(['user_id', 'started_at'], 'idx_user_date');
            $table->index(['subject_area_id', 'started_at'], 'idx_subject_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_sessions');
    }
};
