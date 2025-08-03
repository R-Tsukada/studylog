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
        Schema::create('future_vision_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('future_vision_id')->constrained()->onDelete('cascade');
            $table->enum('benefit_type', ['career', 'personal', 'skill']);
            $table->string('title', 200)->comment('メリットタイトル');
            $table->string('description', 500)->nullable()->comment('メリット詳細');
            $table->unsignedTinyInteger('display_order')->default(1);
            $table->timestamp('created_at')->useCurrent();
            
            // インデックス
            $table->index(['future_vision_id', 'benefit_type', 'display_order'], 'idx_vision_type_order');
            
        });
        
        // PostgreSQL用制約（本番環境用）
        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE future_vision_benefits ADD CONSTRAINT chk_display_order CHECK (display_order BETWEEN 1 AND 20)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('future_vision_benefits');
    }
};
