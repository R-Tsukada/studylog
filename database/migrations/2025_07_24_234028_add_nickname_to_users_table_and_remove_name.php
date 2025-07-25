<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: nickname列を追加（冪等性確保）
        $this->addNicknameColumn();

        // Step 2: 既存データの移行（セキュアなクエリビルダ使用）
        $this->migrateNameToNickname();

        // Step 3: スキーマ変更とクリーンアップ
        $this->finalizeNicknameColumn();
        $this->removeNameColumn();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: name列を復元
        $this->addNameColumn();

        // Step 2: データを復元
        $this->migrateNicknameToName();

        // Step 3: nickname列を削除
        $this->removeNicknameColumn();
    }

    /**
     * nickname列を安全に追加
     */
    private function addNicknameColumn(): void
    {
        if (! Schema::hasColumn('users', 'nickname')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('nickname')->nullable()->after('id');
            });
        }
    }

    /**
     * nameからnicknameへデータを安全に移行
     */
    private function migrateNameToNickname(): void
    {
        if (Schema::hasColumn('users', 'name')) {
            // クエリビルダを使用してSQLインジェクション対策
            DB::table('users')
                ->whereNull('nickname')
                ->orWhere('nickname', '')
                ->update(['nickname' => DB::raw('name')]);
        }
    }

    /**
     * nickname列をnot nullに変更
     */
    private function finalizeNicknameColumn(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->nullable(false)->change();
        });
    }

    /**
     * name列を安全に削除
     */
    private function removeNameColumn(): void
    {
        if (Schema::hasColumn('users', 'name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }
    }

    /**
     * name列を復元
     */
    private function addNameColumn(): void
    {
        if (! Schema::hasColumn('users', 'name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('name')->after('id');
            });
        }
    }

    /**
     * nicknameからnameへデータを復元
     */
    private function migrateNicknameToName(): void
    {
        if (Schema::hasColumn('users', 'nickname')) {
            DB::table('users')
                ->whereNull('name')
                ->orWhere('name', '')
                ->update(['name' => DB::raw('nickname')]);
        }
    }

    /**
     * nickname列を削除
     */
    private function removeNicknameColumn(): void
    {
        if (Schema::hasColumn('users', 'nickname')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('nickname');
            });
        }
    }
};
