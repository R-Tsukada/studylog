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
        Schema::create('study_obstacle_solution_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_obstacle_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('step_order');
            $table->string('description', 500);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('estimated_duration_minutes')->nullable()->comment('予想所要時間（分）');
            $table->unsignedSmallInteger('actual_duration_minutes')->nullable()->comment('実際の所要時間（分）');
            $table->timestamps();
            
            // インデックス
            $table->index(['study_obstacle_id', 'step_order'], 'idx_obstacle_order');
            $table->index(['is_completed', 'completed_at'], 'idx_completed');
            
            // ユニーク制約
            $table->unique(['study_obstacle_id', 'step_order'], 'uk_obstacle_step');
        });
        
        // PostgreSQL用制約（本番環境用）
        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE study_obstacle_solution_steps ADD CONSTRAINT chk_step_order CHECK (step_order BETWEEN 1 AND 50)');
            DB::statement('ALTER TABLE study_obstacle_solution_steps ADD CONSTRAINT chk_estimated_duration CHECK (estimated_duration_minutes IS NULL OR estimated_duration_minutes <= 1440)');
            DB::statement('ALTER TABLE study_obstacle_solution_steps ADD CONSTRAINT chk_actual_duration CHECK (actual_duration_minutes IS NULL OR actual_duration_minutes <= 1440)');
            DB::statement('ALTER TABLE study_obstacle_solution_steps ADD CONSTRAINT chk_completed_at CHECK ((is_completed = false AND completed_at IS NULL) OR (is_completed = true AND completed_at IS NOT NULL))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_obstacle_solution_steps');
    }
};
