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
            // まずnickname列を追加（既存のnameの値をコピー）
            $table->string('nickname')->nullable()->after('id');
        });
        
        // 既存のnameの値をnicknameにコピー
        DB::statement('UPDATE users SET nickname = name WHERE nickname IS NULL');
        
        // nicknameをnot nullに変更
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->nullable(false)->change();
        });
        
        // name列を削除
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // name列を復元
            $table->string('name')->after('id');
        });
        
        // nicknameの値をnameにコピー
        DB::statement('UPDATE users SET name = nickname WHERE name IS NULL');
        
        // nickname列を削除
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nickname');
        });
    }
};
