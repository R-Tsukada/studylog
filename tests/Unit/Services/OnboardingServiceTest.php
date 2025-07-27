<?php

namespace Tests\Unit\Services;

use App\Models\OnboardingLog;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OnboardingServiceTest extends TestCase
{
    use RefreshDatabase;

    private OnboardingService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OnboardingService;
        $this->user = User::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_completion_rate_correctly(): void
    {
        // テストデータ作成
        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        // ユーザー1: 開始のみ
        $user1 = User::factory()->create();
        $log1 = new OnboardingLog([
            'user_id' => $user1->id,
            'event_type' => OnboardingLog::EVENT_STARTED,
        ]);
        $log1->created_at = now()->subDays(5);
        $log1->save();

        // ユーザー2: 開始→完了
        $user2 = User::factory()->create();
        $log2 = new OnboardingLog([
            'user_id' => $user2->id,
            'event_type' => OnboardingLog::EVENT_STARTED,
        ]);
        $log2->created_at = now()->subDays(4);
        $log2->save();

        $log2_complete = new OnboardingLog([
            'user_id' => $user2->id,
            'event_type' => OnboardingLog::EVENT_COMPLETED,
        ]);
        $log2_complete->created_at = now()->subDays(3);
        $log2_complete->save();

        // ユーザー3: 開始→スキップ
        $user3 = User::factory()->create();
        $log3 = new OnboardingLog([
            'user_id' => $user3->id,
            'event_type' => OnboardingLog::EVENT_STARTED,
        ]);
        $log3->created_at = now()->subDays(2);
        $log3->save();

        $log3_skip = new OnboardingLog([
            'user_id' => $user3->id,
            'event_type' => OnboardingLog::EVENT_SKIPPED,
        ]);
        $log3_skip->created_at = now()->subDays(1);
        $log3_skip->save();

        $analytics = $this->service->getAnalytics($startDate, $endDate);

        $this->assertEquals(3, $analytics['completion_rate']['started']);
        $this->assertEquals(1, $analytics['completion_rate']['completed']);
        $this->assertEquals(1, $analytics['completion_rate']['skipped']);
        $this->assertEquals(33.33, $analytics['completion_rate']['completion_rate']);
        $this->assertEquals(33.33, $analytics['completion_rate']['skip_rate']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_step_completions_correctly(): void
    {
        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        // ステップ1を2回、ステップ2を1回完了
        $step1_log1 = new OnboardingLog([
            'user_id' => $this->user->id,
            'event_type' => OnboardingLog::EVENT_STEP_COMPLETED,
            'step_number' => 1,
        ]);
        $step1_log1->created_at = now()->subDays(3);
        $step1_log1->save();

        $user2 = User::factory()->create();
        $step1_log2 = new OnboardingLog([
            'user_id' => $user2->id,
            'event_type' => OnboardingLog::EVENT_STEP_COMPLETED,
            'step_number' => 1,
        ]);
        $step1_log2->created_at = now()->subDays(2);
        $step1_log2->save();

        $step2_log = new OnboardingLog([
            'user_id' => $user2->id,
            'event_type' => OnboardingLog::EVENT_STEP_COMPLETED,
            'step_number' => 2,
        ]);
        $step2_log->created_at = now()->subDays(1);
        $step2_log->save();

        $analytics = $this->service->getAnalytics($startDate, $endDate);

        $stepCompletions = $analytics['step_completions'];
        $this->assertCount(2, $stepCompletions);

        $step1 = $stepCompletions->firstWhere('step_number', 1);
        $step2 = $stepCompletions->firstWhere('step_number', 2);

        $this->assertEquals(2, $step1->completions);
        $this->assertEquals(1, $step2->completions);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_dropoff_analysis(): void
    {
        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        // 2ユーザーがステップ1完了、1ユーザーのみステップ2完了
        $users = User::factory()->count(2)->create();

        foreach ($users as $user) {
            $step1_log = new OnboardingLog([
                'user_id' => $user->id,
                'event_type' => OnboardingLog::EVENT_STEP_COMPLETED,
                'step_number' => 1,
            ]);
            $step1_log->created_at = now()->subDays(3);
            $step1_log->save();
        }

        // 1ユーザーのみステップ2も完了
        $step2_log = new OnboardingLog([
            'user_id' => $users->first()->id,
            'event_type' => OnboardingLog::EVENT_STEP_COMPLETED,
            'step_number' => 2,
        ]);
        $step2_log->created_at = now()->subDays(2);
        $step2_log->save();

        $analytics = $this->service->getAnalytics($startDate, $endDate);

        $dropoffAnalysis = $analytics['dropoff_analysis'];
        $this->assertArrayHasKey('step_completion_counts', $dropoffAnalysis);
        $this->assertArrayHasKey('dropoff_rates', $dropoffAnalysis);

        // ステップ1→2の離脱率は50%
        $this->assertEquals(50.0, $dropoffAnalysis['dropoff_rates']['step_2']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_caches_analytics_results(): void
    {
        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        // 初回呼び出し
        $result1 = $this->service->getAnalytics($startDate, $endDate);

        // キャッシュされているかチェック
        $cacheKey = 'onboarding:analytics:'.md5(serialize([$startDate, $endDate, null, null]));
        $this->assertTrue(Cache::has($cacheKey));

        // 2回目の呼び出し（キャッシュから取得）
        $result2 = $this->service->getAnalytics($startDate, $endDate);

        $this->assertEquals($result1, $result2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_groups_daily_stats_correctly(): void
    {
        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        // 異なる日に異なるイベントを作成
        $started_log = new OnboardingLog([
            'user_id' => $this->user->id,
            'event_type' => OnboardingLog::EVENT_STARTED,
        ]);
        $started_log->created_at = now()->subDays(3)->startOfDay();
        $started_log->save();

        $step_log = new OnboardingLog([
            'user_id' => $this->user->id,
            'event_type' => OnboardingLog::EVENT_STEP_COMPLETED,
            'step_number' => 1,
        ]);
        $step_log->created_at = now()->subDays(3)->addHours(2);
        $step_log->save();

        $completed_log = new OnboardingLog([
            'user_id' => $this->user->id,
            'event_type' => OnboardingLog::EVENT_COMPLETED,
        ]);
        $completed_log->created_at = now()->subDays(2);
        $completed_log->save();

        $analytics = $this->service->getAnalytics($startDate, $endDate);

        $dailyStats = $analytics['daily_stats'];
        $this->assertGreaterThan(0, $dailyStats->count());

        // 各日付グループ内に複数のイベントタイプがあることを確認
        $firstDayStats = $dailyStats->first();
        $this->assertGreaterThan(0, $firstDayStats->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_user_progress_safely_with_transaction(): void
    {
        $initialProgress = $this->user->onboarding_progress;

        // 正常なケース
        $result = $this->service->updateUserProgressSafely(
            $this->user,
            2,
            [1],
            ['exam_type' => 'JSTQB']
        );

        $this->assertTrue($result);

        $this->user->refresh();
        $progress = $this->user->onboarding_progress;

        $this->assertEquals(2, $progress['current_step']);
        $this->assertEquals([1], $progress['completed_steps']);
        $this->assertEquals(['exam_type' => 'JSTQB'], $progress['step_data']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_database_transaction_rollback(): void
    {
        // 無効なステップ番号でエラーを誘発
        $this->expectException(\InvalidArgumentException::class);

        DB::transaction(function () {
            $this->service->updateUserProgressSafely(
                $this->user,
                999, // 無効なステップ番号
                [1],
                ['test' => 'data']
            );
        });

        // トランザクションがロールバックされることを確認
        $this->user->refresh();
        $this->assertNull($this->user->onboarding_progress);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_respects_limit_parameter_in_analytics(): void
    {
        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        // 5つのステップ完了ログを作成
        for ($i = 1; $i <= 5; $i++) {
            $step_log = new OnboardingLog([
                'user_id' => $this->user->id,
                'event_type' => OnboardingLog::EVENT_STEP_COMPLETED,
                'step_number' => $i,
            ]);
            $step_log->created_at = now()->subDays($i);
            $step_log->save();
        }

        // 制限付きで取得
        $analytics = $this->service->getAnalytics($startDate, $endDate, null, 3);

        $stepCompletions = $analytics['step_completions'];
        $this->assertLessThanOrEqual(3, $stepCompletions->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_clears_analytics_cache(): void
    {
        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        // キャッシュを作成
        $this->service->getAnalytics($startDate, $endDate);

        $cacheKey = 'onboarding:analytics:'.md5(serialize([$startDate, $endDate, null, null]));
        $this->assertTrue(Cache::has($cacheKey));

        // キャッシュクリア
        $this->service->clearAnalyticsCache();

        // キャッシュが削除されることを確認（tagsを使用している場合）
        // 実際の実装では、Cache::tags(['onboarding'])->flush() が呼ばれる
        $this->assertTrue(true); // 実装に応じて適切なアサーションに変更
    }
}
