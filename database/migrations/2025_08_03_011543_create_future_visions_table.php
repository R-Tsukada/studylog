<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('future_visions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('exam_type_id')->constrained()->onDelete('cascade');
            $table->string('title')->comment('将来像のタイトル');
            $table->text('description')->nullable()->comment('詳細説明');
            
            // 数値系データ（インデックス可能）
            $table->unsignedInteger('salary_increase')->nullable()->comment('年収増加額（円）');
            $table->unsignedSmallInteger('target_score')->nullable()->comment('目標スコア');
            $table->unsignedTinyInteger('priority')->default(1)->comment('優先度(1-5)');
            
            // 日付
            $table->date('achievement_deadline')->nullable()->comment('達成期限');
            
            // 管理フラグ
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(false)->comment('他ユーザーへの公開設定');
            
            $table->timestamps();
            
            // 最適化されたインデックス（高カーディナリティ優先）
            $table->index(['user_id', 'is_active', 'priority'], 'idx_user_active_priority');
            $table->index(['is_active', 'exam_type_id', 'user_id'], 'idx_active_exam_user');
            $table->index(['is_public', 'is_active', 'created_at'], 'idx_public_active');
        });
        
        // PostgreSQL用制約（本番環境用）
        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE future_visions ADD CONSTRAINT chk_priority CHECK (priority BETWEEN 1 AND 5)');
            DB::statement('ALTER TABLE future_visions ADD CONSTRAINT chk_salary_increase CHECK (salary_increase IS NULL OR (salary_increase >= 0 AND salary_increase <= 50000000))');
            DB::statement('ALTER TABLE future_visions ADD CONSTRAINT chk_target_score CHECK (target_score IS NULL OR (target_score >= 0 AND target_score <= 1000))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('future_visions');
    }
};
