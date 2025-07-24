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
        Schema::table('users', function (Blueprint $table) {
            // Step 1: nicknameカラムを追加（nullableで追加）
            $table->string('nickname', 50)->nullable()->after('name');
        });

        // Step 2: 既存のnameデータをnicknameに移行
        DB::table('users')->whereNull('nickname')->update([
            'nickname' => DB::raw('name')
        ]);

        // Step 3: nicknameをNOT NULLに変更
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname', 50)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nickname');
        });
    }
};
