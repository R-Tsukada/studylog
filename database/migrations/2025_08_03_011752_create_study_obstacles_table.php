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
        Schema::create('study_obstacles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('exam_type_id')->nullable()->constrained()->onDelete('cascade')->comment('特定試験への関連付け（NULL=全般）');
            $table->unsignedTinyInteger('obstacle_category_id');
            
            // 障害情報
            $table->string('obstacle_title')->comment('障害タイトル');
            $table->text('obstacle_description')->nullable()->comment('障害の詳細説明');
            $table->unsignedTinyInteger('severity_level')->default(3)->comment('深刻度(1-5)');
            
            // 対策情報
            $table->string('solution_title')->comment('対策タイトル');
            $table->text('solution_description')->nullable()->comment('対策の詳細説明');
            
            // 実行管理
            $table->boolean('is_active')->default(true);
            $table->boolean('is_resolved')->default(false)->comment('解決済みフラグ');
            $table->unsignedTinyInteger('effectiveness_rating')->nullable()->comment('対策の効果度(1-5)');
            
            // メタデータ
            $table->enum('occurrence_frequency', ['daily', 'weekly', 'monthly', 'rarely'])->default('weekly');
            $table->timestamp('last_occurred_at')->nullable()->comment('最後に発生した日時');
            $table->timestamp('resolved_at')->nullable()->comment('解決日時');
            
            $table->timestamps();
            
            // 外部キー制約
            $table->foreign('obstacle_category_id')->references('id')->on('obstacle_categories');
            
            // 最適化されたインデックス
            $table->index(['user_id', 'is_active', 'is_resolved'], 'idx_user_active_resolved');
            $table->index(['obstacle_category_id', 'severity_level'], 'idx_category_severity');
            $table->index(['is_resolved', 'resolved_at'], 'idx_resolved_date');
            $table->index(['occurrence_frequency', 'last_occurred_at'], 'idx_frequency_occurred');
            
        });
        
        // PostgreSQL用制約（本番環境用）
        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE study_obstacles ADD CONSTRAINT chk_severity_level CHECK (severity_level BETWEEN 1 AND 5)');
            DB::statement('ALTER TABLE study_obstacles ADD CONSTRAINT chk_effectiveness_rating CHECK (effectiveness_rating IS NULL OR effectiveness_rating BETWEEN 1 AND 5)');
            DB::statement('ALTER TABLE study_obstacles ADD CONSTRAINT chk_resolved_at CHECK ((is_resolved = false AND resolved_at IS NULL) OR (is_resolved = true AND resolved_at IS NOT NULL))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_obstacles');
    }
};
