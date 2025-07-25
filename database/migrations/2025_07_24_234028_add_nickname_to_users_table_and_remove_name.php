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
            // nickname列が存在しない場合のみ追加
            if (!Schema::hasColumn('users', 'nickname')) {
                $table->string('nickname')->nullable()->after('id');
            }
        });
        
        // name列が存在する場合、既存のnameの値をnicknameにコピー
        if (Schema::hasColumn('users', 'name')) {
            DB::statement('UPDATE users SET nickname = name WHERE nickname IS NULL OR nickname = ""');
        }
        
        // nicknameをnot nullに変更
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->nullable(false)->change();
        });
        
        // name列が存在する場合のみ削除
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'name')) {
                $table->dropColumn('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // name列が存在しない場合のみ復元
            if (!Schema::hasColumn('users', 'name')) {
                $table->string('name')->after('id');
            }
        });
        
        // nickname列が存在する場合、nicknameの値をnameにコピー
        if (Schema::hasColumn('users', 'nickname')) {
            DB::statement('UPDATE users SET name = nickname WHERE name IS NULL OR name = ""');
        }
        
        // nickname列が存在する場合のみ削除
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'nickname')) {
                $table->dropColumn('nickname');
            }
        });
    }
};
