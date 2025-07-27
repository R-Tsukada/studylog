<?php

namespace Tests\Feature;

use App\Models\OnboardingLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OnboardingControllerExtendedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_respects_login_count_boundary_for_showing_onboarding(): void
    {
        // 境界値テスト：手動でログインカウントを設定してテスト
        $maxLoginCount = config('onboarding.max_login_count', 5);

        // 境界値ちょうど（5回）で表示されることを確認
        $this->user->update(['login_count' => $maxLoginCount]);
        $this->user->refresh();
        $this->assertTrue($this->user->shouldShowOnboarding());

        // 1回オーバー（6回）で表示されないことを確認
        $this->user->update(['login_count' => $maxLoginCount + 1]);
        $this->user->refresh();
        $this->assertFalse($this->user->shouldShowOnboarding());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_respects_registration_date_boundary_for_showing_onboarding(): void
    {
        $showWithinDays = config('onboarding.show_within_days', 30);

        // 境界値テスト：ちょうど30日前に登録
        $this->user->update(['created_at' => now()->subDays($showWithinDays)]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/onboarding/status');

        $response->assertJson([
            'success' => true,
            'data' => ['should_show' => true], // まだ表示される
        ]);

        // 31日前に登録（新しいユーザーインスタンスでテスト）
        $olderUser = User::factory()->create([
            'created_at' => now()->subDays($showWithinDays + 1),
            'login_count' => 0,
        ]);

        $response = $this->actingAs($olderUser)
            ->getJson('/api/onboarding/status');

        $response->assertJson([
            'success' => true,
            'data' => ['should_show' => false], // もう表示されない
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_step_data_size_limit(): void
    {
        $largeData = str_repeat('a', config('onboarding.max_step_data_size', 10240) + 1);

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', [
                'current_step' => 2,
                'step_data' => ['large_field' => $largeData],
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_step_boundaries(): void
    {
        $totalSteps = config('onboarding.total_steps', 4);

        // 範囲外のステップ番号
        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', [
                'current_step' => $totalSteps + 1,
            ]);

        $response->assertStatus(422);

        // 0以下のステップ番号
        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', [
                'current_step' => 0,
            ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_duplicate_step_completion_logs(): void
    {
        // 同じステップを2回完了
        $response1 = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', [
                'current_step' => 2,
                'completed_steps' => [1],
            ]);
        $response1->assertStatus(200);

        $response2 = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', [
                'current_step' => 3,
                'completed_steps' => [1, 2], // ステップ1は既に完了済み
            ]);
        $response2->assertStatus(200);

        // ステップ1の完了ログは1つだけであることを確認
        $step1Logs = OnboardingLog::where('user_id', $this->user->id)
            ->where('event_type', OnboardingLog::EVENT_STEP_COMPLETED)
            ->where('step_number', 1)
            ->count();

        // ログが作成されていることを確認
        $allLogs = OnboardingLog::where('user_id', $this->user->id)->get();
        $this->assertGreaterThan(0, $allLogs->count(), 
            'OnboardingLogが作成されていません。updateOnboardingProgressメソッドのログ記録処理を確認してください。');

        $this->assertEquals(1, $step1Logs);

        // ステップ2のログは1つあることを確認
        $step2Logs = OnboardingLog::where('user_id', $this->user->id)
            ->where('event_type', OnboardingLog::EVENT_STEP_COMPLETED)
            ->where('step_number', 2)
            ->count();

        $this->assertEquals(1, $step2Logs);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_sequential_progress_updates_safely(): void
    {
        // シーケンシャル更新の正常動作検証（トランザクション保護）
        $initialProgress = [
            'current_step' => 1,
            'completed_steps' => [],
        ];

        $this->user->update(['onboarding_progress' => $initialProgress]);

        // 順次2つの更新を実行
        $response1 = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', [
                'current_step' => 2,
                'completed_steps' => [1],
            ]);

        $response2 = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', [
                'current_step' => 3,
                'completed_steps' => [1, 2],
            ]);

        // 両方成功するはず（トランザクション内で安全に処理）
        $response1->assertStatus(200);
        $response2->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_admin_permission_for_analytics(): void
    {
        // 一般ユーザーはアクセス拒否
        $response = $this->actingAs($this->user)
            ->getJson('/api/onboarding/analytics?'.http_build_query([
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
            ]));

        $response->assertStatus(403);

        // 管理者はアクセス可能
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/onboarding/analytics?'.http_build_query([
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
            ]));

        $response->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_analytics_date_ranges(): void
    {
        // 開始日が終了日より後
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/onboarding/analytics?'.http_build_query([
                'start_date' => now()->toDateString(),
                'end_date' => now()->subDays(1)->toDateString(),
            ]));

        $response->assertStatus(422);

        // 1年より古い日付
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/onboarding/analytics?'.http_build_query([
                'start_date' => now()->subYear()->subDay()->toDateString(),
                'end_date' => now()->toDateString(),
            ]));

        $response->assertStatus(422);

        // 未来の日付
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/onboarding/analytics?'.http_build_query([
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
            ]));

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_limits_analytics_results(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/onboarding/analytics?'.http_build_query([
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
                'limit' => 1001, // 制限オーバー
            ]));

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_completing_onboarding_multiple_times(): void
    {
        // 一度完了
        $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', [
                'completed_steps' => [1, 2, 3, 4],
            ]);

        $this->user->refresh();
        $this->assertNotNull($this->user->onboarding_completed_at);

        // 再度完了を試行
        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', [
                'completed_steps' => [1, 2, 3, 4],
            ]);

        // 成功し、2回目の完了ログも作成される
        $response->assertStatus(200);

        $completionLogs = OnboardingLog::where('user_id', $this->user->id)
            ->where('event_type', OnboardingLog::EVENT_COMPLETED)
            ->count();

        // 現在の実装では重複完了が許可されており、2つのログが記録される
        $this->assertEquals(2, $completionLogs);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_skip_reason_enum(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/skip', [
                'current_step' => 2,
                'reason' => 'invalid_reason',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['reason']]);

        // 有効な理由
        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/skip', [
                'current_step' => 2,
                'reason' => 'too_complex',
            ]);

        $response->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_session_login_count_correctly(): void
    {
        $initialLoginCount = $this->user->login_count;

        // 同じセッション内で複数回status APIを呼び出し
        $this->actingAs($this->user)->getJson('/api/onboarding/status');
        $this->actingAs($this->user)->getJson('/api/onboarding/status');
        $this->actingAs($this->user)->getJson('/api/onboarding/status');

        $this->user->refresh();

        // ログイン回数は1回のみ増加
        $this->assertEquals($initialLoginCount + 1, $this->user->login_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_proper_error_logging(): void
    {
        // Laravelのログファサードをモック化してエラーログ記録を検証
        Log::shouldReceive('error')
            ->once()
            ->with(
                'Onboarding API Error',
                \Mockery::on(function ($context) {
                    return is_array($context) &&
                           isset($context['user_id']) &&
                           isset($context['endpoint']) &&
                           isset($context['method']) &&
                           $context['endpoint'] === '/api/onboarding/progress';
                })
            );

        // 無効なデータでAPIエラーを誘発（バリデーションエラーではなく、システムエラーを狙う）
        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', [
                'current_step' => 999, // 存在しないステップ番号
                'completed_steps' => [1, 2, 3],
            ]);

        $response->assertStatus(422);
    }
}
