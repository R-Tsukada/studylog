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
        Schema::table('users', function (Blueprint $table) {
            // オンボーディング関連カラム
            $table->timestamp('onboarding_completed_at')->nullable()
                ->after('updated_at')
                ->comment('オンボーディング完了日時');

            $table->json('onboarding_progress')->nullable()
                ->after('onboarding_completed_at')
                ->comment('進捗データ（JSON）');

            $table->boolean('onboarding_skipped')->default(false)
                ->after('onboarding_progress')
                ->comment('スキップフラグ');

            $table->string('onboarding_version', 10)->default('1.0')
                ->after('onboarding_skipped')
                ->comment('オンボーディングバージョン');

            $table->unsignedTinyInteger('login_count')->default(0)
                ->after('onboarding_version')
                ->comment('ログイン回数');

            // インデックス追加
            $table->index('onboarding_completed_at', 'idx_users_onboarding_completed');
            $table->index(['onboarding_skipped', 'created_at'], 'idx_users_onboarding_skipped');
            $table->index('login_count', 'idx_users_login_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_onboarding_completed');
            $table->dropIndex('idx_users_onboarding_skipped');
            $table->dropIndex('idx_users_login_count');

            $table->dropColumn([
                'onboarding_completed_at',
                'onboarding_progress',
                'onboarding_skipped',
                'onboarding_version',
                'login_count',
            ]);
        });
    }
};
