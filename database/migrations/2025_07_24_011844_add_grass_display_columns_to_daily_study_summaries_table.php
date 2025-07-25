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
        Schema::table('daily_study_summaries', function (Blueprint $table) {
            // 学習開始/終了で計測した時間（分）
            $table->integer('study_session_minutes')->default(0)->after('total_minutes')
                ->comment('学習開始/終了で計測した合計時間（分）');

            // ポモドーロで計測した時間（分）
            $table->integer('pomodoro_minutes')->default(0)->after('study_session_minutes')
                ->comment('ポモドーロで計測した合計時間（分）');

            // 完了したフォーカスセッション数
            $table->integer('total_focus_sessions')->default(0)->after('pomodoro_minutes')
                ->comment('完了したフォーカスセッション数');

            // 草表示用レベル（0-3）
            $table->tinyInteger('grass_level')->default(0)->after('total_focus_sessions')
                ->comment('草表示レベル（0:なし、1:薄い、2:中間、3:濃い）');

            // 学習ストリーク用（後の拡張用）
            $table->integer('streak_days')->default(0)->after('grass_level')
                ->comment('連続学習日数（将来の拡張用）');
        });

        // インデックス追加
        Schema::table('daily_study_summaries', function (Blueprint $table) {
            $table->index(['user_id', 'study_date', 'grass_level'], 'idx_user_date_grass');
            $table->index(['grass_level', 'study_date'], 'idx_grass_level_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_study_summaries', function (Blueprint $table) {
            $table->dropIndex('idx_user_date_grass');
            $table->dropIndex('idx_grass_level_date');

            $table->dropColumn([
                'study_session_minutes',
                'pomodoro_minutes',
                'total_focus_sessions',
                'grass_level',
                'streak_days',
            ]);
        });
    }
};
