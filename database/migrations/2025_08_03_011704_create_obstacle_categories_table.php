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
        Schema::create('obstacle_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->string('description', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->timestamp('created_at')->useCurrent();
            
            // インデックス
            $table->index(['is_active', 'sort_order'], 'idx_active_sort');
        });
        
        // 初期データの挿入
        DB::table('obstacle_categories')->insert([
            ['code' => 'time', 'name' => '時間管理', 'description' => '勉強時間の確保や管理に関する問題', 'sort_order' => 1],
            ['code' => 'motivation', 'name' => 'モチベーション', 'description' => 'やる気や継続意欲に関する問題', 'sort_order' => 2],
            ['code' => 'difficulty', 'name' => '学習難易度', 'description' => '内容の理解や習得に関する問題', 'sort_order' => 3],
            ['code' => 'environment', 'name' => '環境', 'description' => '学習環境や外的要因に関する問題', 'sort_order' => 4],
            ['code' => 'health', 'name' => '健康', 'description' => '体調や健康状態に関する問題', 'sort_order' => 5],
            ['code' => 'other', 'name' => 'その他', 'description' => 'その他の要因', 'sort_order' => 99],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obstacle_categories');
    }
};
