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
        // 既存の非ユニークインデックスを削除（存在する場合）
        if (Schema::hasIndex('user_future_visions', 'idx_user_future_visions_user_id')) {
            Schema::table('user_future_visions', function (Blueprint $table) {
                $table->dropIndex('idx_user_future_visions_user_id');
            });
        }

        Schema::table('user_future_visions', function (Blueprint $table) {
            // ユニーク制約を追加
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_future_visions', function (Blueprint $table) {
            // ユニーク制約を削除
            $table->dropUnique(['user_id']);
        });

        // 元の非ユニークインデックスを復元（既存の場合はスキップ）
        if (! Schema::hasIndex('user_future_visions', 'idx_user_future_visions_user_id')) {
            Schema::table('user_future_visions', function (Blueprint $table) {
                $table->index('user_id', 'idx_user_future_visions_user_id');
            });
        }
    }
};
