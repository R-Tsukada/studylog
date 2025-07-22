<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\StudySession;
use App\Models\PomodoroSession;
use App\Models\ExamType;
use App\Models\SubjectArea;
use App\Services\StudyActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

/**
 * 統合分析システム全体の結合テスト
 * 
 * 時間計測・ポモドーロ・統合分析機能が連携して正常に動作することを検証
 */
class IntegratedAnalyticsSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;
    private ExamType $examType1;
    private ExamType $examType2;
    private SubjectArea $subjectArea1;
    private SubjectArea $subjectArea2;
    private StudyActivityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new StudyActivityService();
        
        // テストユーザー作成
        $this->user1 = User::factory()->create(['name' => 'テストユーザー1']);
        $this->user2 = User::factory()->create(['name' => 'テストユーザー2']);
        
        // テスト試験タイプ作成
        $this->examType1 = ExamType::factory()->create(['name' => 'JSTQB Foundation']);
        $this->examType2 = ExamType::factory()->create(['name' => 'AWS Solutions Architect']);
        
        // テスト学習分野作成
        $this->subjectArea1 = SubjectArea::factory()->create([
            'exam_type_id' => $this->examType1->id,
            'name' => 'テスト設計技法'
        ]);
        $this->subjectArea2 = SubjectArea::factory()->create([
            'exam_type_id' => $this->examType2->id,
            'name' => 'EC2とVPC'
        ]);
    }

    /**
     * @test
     * 基本的な統合分析機能のテスト（簡略版）
     */
    public function basic_integrated_analytics_functionality()
    {
        Sanctum::actingAs($this->user1);
        
        // シンプルなデータを作成
        $this->createStudySession($this->user1, $this->subjectArea1, Carbon::now()->subHours(2), 60);
        $this->createPomodoroSequence($this->user1, $this->subjectArea2, Carbon::now()->subHours(1), 1);
        
        // 統合履歴テスト
        $historyResponse = $this->getJson('/api/analytics/history');
        $historyResponse->assertOk();
        $historyData = $historyResponse->json('data');
        
        $this->assertGreaterThanOrEqual(2, count($historyData));
        $this->assertContains('time_tracking', collect($historyData)->pluck('type')->toArray());
        $this->assertContains('pomodoro', collect($historyData)->pluck('type')->toArray());
        
        // 統合統計テスト
        $statsResponse = $this->getJson('/api/analytics/stats');
        $statsResponse->assertOk();
        $statsData = $statsResponse->json('data');
        
        $this->assertArrayHasKey('overview', $statsData);
        $this->assertArrayHasKey('by_method', $statsData);
        $this->assertGreaterThan(0, $statsData['overview']['total_study_time']);
        
        // インサイトテスト
        $insightsResponse = $this->getJson('/api/analytics/insights');
        $insightsResponse->assertOk();
        $insightsData = $insightsResponse->json('data');
        
        $this->assertArrayHasKey('preferred_method', $insightsData);
        $this->assertArrayHasKey('productivity_trends', $insightsData);
        $this->assertArrayHasKey('recommendations', $insightsData);
        
        // 推奨テスト
        $suggestionResponse = $this->getJson('/api/analytics/suggest');
        $suggestionResponse->assertOk();
        $suggestionData = $suggestionResponse->json('data');
        
        $this->assertArrayHasKey('recommended', $suggestionData);
        $this->assertContains($suggestionData['recommended']['method'], ['time_tracking', 'pomodoro']);
    }

    /**
     * @test
     * 複数ユーザーでのデータ分離テスト
     */
    public function user_data_isolation_in_integrated_analytics()
    {
        // User1のデータ作成
        $this->createStudySession($this->user1, $this->subjectArea1, Carbon::now()->subHours(2), 60);
        $this->createPomodoroSequence($this->user1, $this->subjectArea1, Carbon::now()->subHour(), 2);
        
        // User2のデータ作成
        $this->createStudySession($this->user2, $this->subjectArea2, Carbon::now()->subHours(3), 90);
        $this->createPomodoroSequence($this->user2, $this->subjectArea2, Carbon::now()->subHours(2), 3);
        
        // User1として認証
        Sanctum::actingAs($this->user1);
        
        $user1StatsResponse = $this->getJson('/api/analytics/stats');
        $user1StatsResponse->assertOk();
        $user1Stats = $user1StatsResponse->json('data');
        
        // User1のデータのみが取得されることを確認
        $expectedUser1Time = 60 + (2 * 25); // 110分
        $this->assertEquals($expectedUser1Time, $user1Stats['overview']['total_study_time']);
        $this->assertGreaterThanOrEqual(3, $user1Stats['overview']['total_sessions']); // 1 + 2以上（休憩セッション含む）
        
        // User2として認証
        Sanctum::actingAs($this->user2);
        
        $user2StatsResponse = $this->getJson('/api/analytics/stats');
        $user2StatsResponse->assertOk();
        $user2Stats = $user2StatsResponse->json('data');
        
        // User2のデータのみが取得されることを確認
        $expectedUser2Time = 90 + (3 * 25); // 165分
        $this->assertEquals($expectedUser2Time, $user2Stats['overview']['total_study_time']);
        $this->assertGreaterThanOrEqual(4, $user2Stats['overview']['total_sessions']); // 1 + 3以上（休憩セッション含む）
        
        // データが完全に分離されていることを確認
        $this->assertNotEquals($user1Stats['overview']['total_study_time'], $user2Stats['overview']['total_study_time']);
    }

    /**
     * @test
     * パフォーマンステスト（軽量版）
     */
    public function light_performance_test()
    {
        Sanctum::actingAs($this->user1);
        
        // 少量のデータを作成（1週間分）
        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::now()->subDays($i);
            $this->createStudySession($this->user1, $this->subjectArea1, $date, 60);
        }
        
        // パフォーマンステスト実行
        $startTime = microtime(true);
        
        $statsResponse = $this->getJson('/api/analytics/stats');
        $statsResponse->assertOk();
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // 1秒以内で完了することを確認
        $this->assertLessThan(1.0, $executionTime);
        
        $statsData = $statsResponse->json('data');
        $this->assertGreaterThan(0, $statsData['overview']['total_study_time']);
        $this->assertGreaterThan(0, $statsData['overview']['total_sessions']);
    }

    /**
     * @test
     * エッジケースでの統合分析テスト
     */
    public function edge_cases_in_integrated_analytics()
    {
        Sanctum::actingAs($this->user1);
        
        // === ケース1: 中断されたポモドーロセッションの処理 ===
        PomodoroSession::factory()->create([
            'user_id' => $this->user1->id,
            'subject_area_id' => $this->subjectArea1->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'actual_duration' => 10, // 途中で中断
            'started_at' => Carbon::now()->subHours(2),
            'completed_at' => Carbon::now()->subHours(2)->addMinutes(10),
            'is_completed' => true,
            'was_interrupted' => true
        ]);
        
        // === ケース2: 非常に短いセッション ===
        StudySession::factory()->create([
            'user_id' => $this->user1->id,
            'subject_area_id' => $this->subjectArea1->id,
            'started_at' => Carbon::now()->subHours(1),
            'ended_at' => Carbon::now()->subHours(1)->addMinutes(1),
            'duration_minutes' => 1,
            'study_comment' => '短時間テスト'
        ]);
        
        // === ケース3: 非常に長いセッション ===
        StudySession::factory()->create([
            'user_id' => $this->user1->id,
            'subject_area_id' => $this->subjectArea2->id,
            'started_at' => Carbon::now()->subHours(8),
            'ended_at' => Carbon::now()->subHours(2),
            'duration_minutes' => 360, // 6時間
            'study_comment' => '長時間学習'
        ]);
        
        // === ケース4: 深夜のセッション ===
        StudySession::factory()->create([
            'user_id' => $this->user1->id,
            'subject_area_id' => $this->subjectArea1->id,
            'started_at' => Carbon::now()->subDay()->setHour(23),
            'ended_at' => Carbon::now()->setHour(1),
            'duration_minutes' => 120,
            'study_comment' => '深夜学習'
        ]);
        
        // 統合分析がエラーなく動作することを確認
        $statsResponse = $this->getJson('/api/analytics/stats');
        $statsResponse->assertOk();
        $statsData = $statsResponse->json('data');
        
        // エッジケースが適切に処理されていることを確認
        $this->assertEquals(4, $statsData['overview']['total_sessions']);
        $this->assertEquals(491, $statsData['overview']['total_study_time']); // 実際の合計時間（ポモドーロ10分含む）
        
        // ポモドーロ完了率が正しく計算されているか（中断あり）
        $this->assertEquals(0.0, $statsData['by_method']['pomodoro']['completion_rate']); // 100%中断
        
        // 推奨システムがエラーなく動作するか
        $suggestionResponse = $this->getJson('/api/analytics/suggest');
        $suggestionResponse->assertOk();
        
        // 長時間セッションがあるため、時間計測が推奨される可能性が高い
        $suggestionData = $suggestionResponse->json('data');
        $this->assertArrayHasKey('recommended', $suggestionData);
    }

    /**
     * @test
     * 統合分析とリアルタイム推奨の連携テスト
     */
    public function real_time_suggestion_with_historical_data()
    {
        Sanctum::actingAs($this->user1);
        
        // === 学習パターンを作成 ===
        
        // パターン1: 平日は短時間のポモドーロ
        for ($i = 0; $i < 5; $i++) {
            $this->createPomodoroSequence(
                $this->user1,
                $this->subjectArea1,
                Carbon::now()->subDays(10 - $i)->setHour(19), // 平日夜
                2 // 短時間
            );
        }
        
        // パターン2: 週末は長時間の時間計測
        $this->createStudySession(
            $this->user1,
            $this->subjectArea1,
            Carbon::now()->subDays(9)->setHour(10), // 土曜朝
            180 // 3時間
        );
        
        $this->createStudySession(
            $this->user1,
            $this->subjectArea2,
            Carbon::now()->subDays(8)->setHour(14), // 日曜午後
            240 // 4時間
        );
        
        // === 現在時刻に応じた推奨テスト ===
        
        // 平日夜の推奨取得
        Carbon::setTestNow(Carbon::now()->next(Carbon::FRIDAY)->setHour(19));
        $fridayEveningResponse = $this->getJson('/api/analytics/suggest');
        $fridayEveningResponse->assertOk();
        $fridayEveningSuggestion = $fridayEveningResponse->json('data');
        
        // 平日夜はポモドーロが推奨される可能性が高い
        $this->assertArrayHasKey('recommended', $fridayEveningSuggestion);
        
        // 週末朝の推奨取得
        Carbon::setTestNow(Carbon::now()->next(Carbon::SATURDAY)->setHour(10));
        $saturdayMorningResponse = $this->getJson('/api/analytics/suggest');
        $saturdayMorningResponse->assertOk();
        $saturdayMorningSuggestion = $saturdayMorningResponse->json('data');
        
        // コンテキスト情報が適切に設定されているか
        $this->assertEquals(10, $saturdayMorningSuggestion['context']['time_of_day']);
        $this->assertGreaterThan(0, $saturdayMorningSuggestion['context']['recent_avg_duration']);
        
        // 学習分野指定での推奨テスト
        $subjectSpecificResponse = $this->getJson("/api/analytics/suggest?subject_area_id={$this->subjectArea1->id}");
        $subjectSpecificResponse->assertOk();
        $subjectSpecificSuggestion = $subjectSpecificResponse->json('data');
        
        $this->assertArrayHasKey('recommended', $subjectSpecificSuggestion);
        $this->assertIsFloat($subjectSpecificSuggestion['recommended']['confidence']);
        $this->assertGreaterThanOrEqual(0, $subjectSpecificSuggestion['recommended']['confidence']);
        $this->assertLessThanOrEqual(1, $subjectSpecificSuggestion['recommended']['confidence']);
        
        // テスト時刻をリセット
        Carbon::setTestNow();
    }

    // === ヘルパーメソッド ===

    /**
     * 時間計測セッションを作成
     */
    private function createStudySession(User $user, SubjectArea $subjectArea, Carbon $startTime, int $durationMinutes): StudySession
    {
        return StudySession::factory()->create([
            'user_id' => $user->id,
            'subject_area_id' => $subjectArea->id,
            'started_at' => $startTime,
            'ended_at' => $startTime->copy()->addMinutes($durationMinutes),
            'duration_minutes' => $durationMinutes,
            'study_comment' => "テスト学習 - {$subjectArea->name}"
        ]);
    }

    /**
     * ポモドーロセッション列を作成（集中+休憩のセット）
     */
    private function createPomodoroSequence(User $user, SubjectArea $subjectArea, Carbon $startTime, int $focusSessions): array
    {
        $sessions = [];
        $currentTime = $startTime->copy();

        for ($i = 0; $i < $focusSessions; $i++) {
            // 集中セッション
            $sessions[] = PomodoroSession::factory()->create([
                'user_id' => $user->id,
                'subject_area_id' => $subjectArea->id,
                'session_type' => 'focus',
                'planned_duration' => 25,
                'actual_duration' => 25,
                'started_at' => $currentTime->copy(),
                'completed_at' => $currentTime->copy()->addMinutes(25),
                'is_completed' => true,
                'was_interrupted' => false
            ]);

            $currentTime->addMinutes(25);

            // 休憩セッション（最後以外）
            if ($i < $focusSessions - 1) {
                $breakType = ($i + 1) % 4 === 0 ? 'long_break' : 'short_break';
                $breakDuration = $breakType === 'long_break' ? 15 : 5;

                $sessions[] = PomodoroSession::factory()->create([
                    'user_id' => $user->id,
                    'subject_area_id' => null, // 休憩は学習分野なし
                    'session_type' => $breakType,
                    'planned_duration' => $breakDuration,
                    'actual_duration' => $breakDuration,
                    'started_at' => $currentTime->copy(),
                    'completed_at' => $currentTime->copy()->addMinutes($breakDuration),
                    'is_completed' => true,
                    'was_interrupted' => false
                ]);

                $currentTime->addMinutes($breakDuration);
            }
        }

        return $sessions;
    }
}