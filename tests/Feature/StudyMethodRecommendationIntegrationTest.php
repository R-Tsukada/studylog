<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\StudySession;
use App\Models\PomodoroSession;
use App\Models\ExamType;
use App\Models\SubjectArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

/**
 * 学習手法推奨システムの結合テスト
 * 
 * 実際の学習パターンに基づく推奨システムの動作を検証
 */
class StudyMethodRecommendationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ExamType $examType;
    private SubjectArea $mathSubject;
    private SubjectArea $programmingSubject;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create(['name' => '推奨テストユーザー']);
        $this->examType = ExamType::factory()->create(['name' => 'IT基礎']);
        
        $this->mathSubject = SubjectArea::factory()->create([
            'exam_type_id' => $this->examType->id,
            'name' => '数学・論理'
        ]);
        
        $this->programmingSubject = SubjectArea::factory()->create([
            'exam_type_id' => $this->examType->id,
            'name' => 'プログラミング'
        ]);
    }

    /**
     * @test
     * 長時間学習者への時間計測推奨テスト
     */
    public function recommends_time_tracking_for_long_session_learner()
    {
        Sanctum::actingAs($this->user);
        
        // 長時間学習の履歴を作成
        for ($i = 0; $i < 7; $i++) {
            StudySession::factory()->create([
                'user_id' => $this->user->id,
                'subject_area_id' => $this->mathSubject->id,
                'started_at' => Carbon::now()->subDays($i + 1),
                'ended_at' => Carbon::now()->subDays($i + 1)->addHours(2),
                'duration_minutes' => 120 + rand(-30, 30), // 90-150分
                'study_comment' => "長時間学習 day {$i}"
            ]);
        }
        
        // 推奨を取得
        $response = $this->getJson('/api/analytics/suggest');
        $response->assertOk();
        
        $suggestion = $response->json('data');
        
        // 長時間学習の履歴があるため、時間計測が推奨される可能性が高い
        $hasTimeTrackingSuggestion = $suggestion['recommended']['method'] === 'time_tracking' ||
            collect($suggestion['alternatives'])->contains('method', 'time_tracking');
        
        $this->assertTrue($hasTimeTrackingSuggestion, '長時間学習履歴があるユーザーに時間計測が推奨されませんでした');
        
        // 推奨理由が適切か確認
        if ($suggestion['recommended']['method'] === 'time_tracking') {
            $this->assertStringContainsString('長時間', $suggestion['recommended']['reason']);
        }
        
        // コンテキスト情報の確認
        $this->assertGreaterThan(100, $suggestion['context']['recent_avg_duration']);
        $this->assertEquals('time_tracking', $suggestion['context']['recent_method']);
    }

    /**
     * @test
     * 短時間集中学習者へのポモドーロ推奨テスト
     */
    public function recommends_pomodoro_for_short_burst_learner()
    {
        Sanctum::actingAs($this->user);
        
        // 短時間集中学習の履歴を作成
        for ($i = 0; $i < 10; $i++) {
            $sessionCount = rand(2, 4);
            $date = Carbon::now()->subDays($i + 1);
            
            $this->createPomodoroSequence($this->user, $this->programmingSubject, $date, $sessionCount);
        }
        
        // 推奨を取得
        $response = $this->getJson('/api/analytics/suggest');
        $response->assertOk();
        
        $suggestion = $response->json('data');
        
        // ポモドーロの履歴があるため、ポモドーロが推奨される可能性が高い
        $hasPomodoroSuggestion = $suggestion['recommended']['method'] === 'pomodoro' ||
            collect($suggestion['alternatives'])->contains('method', 'pomodoro');
        
        $this->assertTrue($hasPomodoroSuggestion, '短時間集中履歴があるユーザーにポモドーロが推奨されませんでした');
        
        // コンテキスト情報の確認
        $this->assertLessThan(60, $suggestion['context']['recent_avg_duration']); // 平均25分程度
        $this->assertEquals('pomodoro', $suggestion['context']['recent_method']);
    }

    /**
     * @test
     * 時刻に基づく推奨テスト
     */
    public function recommends_based_on_time_of_day()
    {
        Sanctum::actingAs($this->user);
        
        // 基本的な履歴を作成（推奨に影響を与えない程度）
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->mathSubject->id,
            'started_at' => Carbon::now()->subDays(3),
            'ended_at' => Carbon::now()->subDays(3)->addHour(),
            'duration_minutes' => 60,
            'study_comment' => 'ベース学習'
        ]);
        
        // 午後の時間帯でテスト（13-17時はポモドーロ推奨）
        Carbon::setTestNow(Carbon::now()->setHour(15));
        
        $afternoonResponse = $this->getJson('/api/analytics/suggest');
        $afternoonResponse->assertOk();
        $afternoonSuggestion = $afternoonResponse->json('data');
        
        // 午後の推奨を確認
        $this->assertEquals(15, $afternoonSuggestion['context']['time_of_day']);
        
        // 午後にポモドーロが推奨されているかチェック
        $hasPomodoroForAfternoon = $afternoonSuggestion['recommended']['method'] === 'pomodoro' ||
            collect($afternoonSuggestion['alternatives'])->contains('method', 'pomodoro');
        
        if ($hasPomodoroForAfternoon) {
            $pomodoroSuggestion = $afternoonSuggestion['recommended']['method'] === 'pomodoro' 
                ? $afternoonSuggestion['recommended']
                : collect($afternoonSuggestion['alternatives'])->firstWhere('method', 'pomodoro');
                
            $this->assertStringContainsString('午後', $pomodoroSuggestion['reason']);
        }
        
        // 朝の時間帯でテスト
        Carbon::setTestNow(Carbon::now()->setHour(9));
        
        $morningResponse = $this->getJson('/api/analytics/suggest');
        $morningResponse->assertOk();
        $morningSuggestion = $morningResponse->json('data');
        
        $this->assertEquals(9, $morningSuggestion['context']['time_of_day']);
        
        // テスト時刻をリセット
        Carbon::setTestNow();
    }

    /**
     * @test
     * 学習分野指定時の推奨テスト
     */
    public function recommends_based_on_subject_area_history()
    {
        Sanctum::actingAs($this->user);
        
        // 数学分野では時間計測を多用
        for ($i = 0; $i < 5; $i++) {
            StudySession::factory()->create([
                'user_id' => $this->user->id,
                'subject_area_id' => $this->mathSubject->id,
                'started_at' => Carbon::now()->subDays($i + 1),
                'ended_at' => Carbon::now()->subDays($i + 1)->addHours(2),
                'duration_minutes' => 120,
                'study_comment' => "数学学習 {$i}"
            ]);
        }
        
        // プログラミング分野ではポモドーロを多用
        for ($i = 0; $i < 5; $i++) {
            $this->createPomodoroSequence(
                $this->user,
                $this->programmingSubject,
                Carbon::now()->subDays($i + 1)->addHours(3),
                3
            );
        }
        
        // 数学分野での推奨取得
        $mathResponse = $this->getJson("/api/analytics/suggest?subject_area_id={$this->mathSubject->id}");
        $mathResponse->assertOk();
        $mathSuggestion = $mathResponse->json('data');
        
        // プログラミング分野での推奨取得
        $programmingResponse = $this->getJson("/api/analytics/suggest?subject_area_id={$this->programmingSubject->id}");
        $programmingResponse->assertOk();
        $programmingSuggestion = $programmingResponse->json('data');
        
        // 両方とも推奨が返されることを確認
        $this->assertArrayHasKey('recommended', $mathSuggestion);
        $this->assertArrayHasKey('recommended', $programmingSuggestion);
        
        // 信頼度が適切に設定されていることを確認
        $this->assertGreaterThan(0, $mathSuggestion['recommended']['confidence']);
        $this->assertGreaterThan(0, $programmingSuggestion['recommended']['confidence']);
    }

    /**
     * @test
     * 混合学習パターンでの推奨テスト
     */
    public function recommends_for_mixed_learning_pattern()
    {
        Sanctum::actingAs($this->user);
        
        // 混合パターン: 平日はポモドーロ、週末は時間計測
        $currentDate = Carbon::now()->startOfWeek();
        
        // 平日（月-金）: ポモドーロ
        for ($i = 0; $i < 5; $i++) {
            $this->createPomodoroSequence(
                $this->user,
                $this->programmingSubject,
                $currentDate->copy()->addDays($i)->setHour(19),
                2
            );
        }
        
        // 週末（土日）: 時間計測
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->mathSubject->id,
            'started_at' => $currentDate->copy()->addDays(5)->setHour(10), // 土曜
            'ended_at' => $currentDate->copy()->addDays(5)->setHour(13),
            'duration_minutes' => 180,
            'study_comment' => '週末長時間学習'
        ]);
        
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->mathSubject->id,
            'started_at' => $currentDate->copy()->addDays(6)->setHour(14), // 日曜
            'ended_at' => $currentDate->copy()->addDays(6)->setHour(17),
            'duration_minutes' => 180,
            'study_comment' => '日曜午後学習'
        ]);
        
        // 平日夜での推奨取得
        Carbon::setTestNow($currentDate->copy()->addDays(7)->setHour(19)); // 次週月曜夜
        
        $weekdayResponse = $this->getJson('/api/analytics/suggest');
        $weekdayResponse->assertOk();
        $weekdaySuggestion = $weekdayResponse->json('data');
        
        // 週末朝での推奨取得
        Carbon::setTestNow($currentDate->copy()->addDays(12)->setHour(10)); // 次週土曜朝
        
        $weekendResponse = $this->getJson('/api/analytics/suggest');
        $weekendResponse->assertOk();
        $weekendSuggestion = $weekendResponse->json('data');
        
        // 両パターンで適切な推奨が返されることを確認
        $this->assertArrayHasKey('recommended', $weekdaySuggestion);
        $this->assertArrayHasKey('recommended', $weekendSuggestion);
        
        // コンテキスト情報が適切に設定されていることを確認
        $this->assertEquals(19, $weekdaySuggestion['context']['time_of_day']);
        $this->assertEquals(10, $weekendSuggestion['context']['time_of_day']);
        
        // テスト時刻をリセット
        Carbon::setTestNow();
    }

    /**
     * @test
     * 初回ユーザーへのデフォルト推奨テスト
     */
    public function provides_default_recommendation_for_new_user()
    {
        $newUser = User::factory()->create(['name' => '新規ユーザー']);
        Sanctum::actingAs($newUser);
        
        // 履歴なしで推奨を取得
        $response = $this->getJson('/api/analytics/suggest');
        $response->assertOk();
        
        $suggestion = $response->json('data');
        
        // デフォルト推奨が返されることを確認
        $this->assertArrayHasKey('recommended', $suggestion);
        $this->assertContains($suggestion['recommended']['method'], ['time_tracking', 'pomodoro']);
        $this->assertGreaterThan(0, $suggestion['recommended']['confidence']);
        
        // 初回ユーザー向けの理由が含まれていることを確認
        $this->assertStringContainsString('初回', $suggestion['recommended']['reason']);
        
        // コンテキスト情報が適切に設定されていることを確認
        $this->assertLessThanOrEqual(30, $suggestion['context']['recent_avg_duration']); // 新規ユーザーでもデフォルト値が設定される場合
        $this->assertNull($suggestion['context']['recent_method']);
    }

    /**
     * @test
     * 推奨システムのレスポンス時間テスト
     */
    public function recommendation_response_time_is_acceptable()
    {
        Sanctum::actingAs($this->user);
        
        // 中程度の履歴を作成
        for ($i = 0; $i < 10; $i++) {
            if ($i % 2 === 0) {
                StudySession::factory()->create([
                    'user_id' => $this->user->id,
                    'subject_area_id' => $this->mathSubject->id,
                    'started_at' => Carbon::now()->subDays($i + 1),
                    'ended_at' => Carbon::now()->subDays($i + 1)->addHour(),
                    'duration_minutes' => 60,
                    'study_comment' => "学習 {$i}"
                ]);
            } else {
                $this->createPomodoroSequence(
                    $this->user,
                    $this->programmingSubject,
                    Carbon::now()->subDays($i + 1),
                    2
                );
            }
        }
        
        // レスポンス時間を測定
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/analytics/suggest');
        $response->assertOk();
        
        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;
        
        // 0.5秒以内で応答することを確認
        $this->assertLessThan(0.5, $responseTime, "推奨API のレスポンス時間が{$responseTime}秒でした（基準: 0.5秒以内）");
        
        $suggestion = $response->json('data');
        $this->assertArrayHasKey('recommended', $suggestion);
    }

    /**
     * @test
     * 異なる学習分野での推奨一貫性テスト
     */
    public function recommendation_consistency_across_subjects()
    {
        Sanctum::actingAs($this->user);
        
        // 全分野で同様の学習パターンを作成
        foreach ([$this->mathSubject, $this->programmingSubject] as $subject) {
            for ($i = 0; $i < 3; $i++) {
                StudySession::factory()->create([
                    'user_id' => $this->user->id,
                    'subject_area_id' => $subject->id,
                    'started_at' => Carbon::now()->subDays($i + 1),
                    'ended_at' => Carbon::now()->subDays($i + 1)->addHours(2),
                    'duration_minutes' => 120,
                    'study_comment' => "共通パターン学習"
                ]);
            }
        }
        
        // 各分野での推奨を取得
        $mathResponse = $this->getJson("/api/analytics/suggest?subject_area_id={$this->mathSubject->id}");
        $programmingResponse = $this->getJson("/api/analytics/suggest?subject_area_id={$this->programmingSubject->id}");
        $generalResponse = $this->getJson('/api/analytics/suggest');
        
        $mathResponse->assertOk();
        $programmingResponse->assertOk();
        $generalResponse->assertOk();
        
        $mathSuggestion = $mathResponse->json('data');
        $programmingSuggestion = $programmingResponse->json('data');
        $generalSuggestion = $generalResponse->json('data');
        
        // 全ての推奨が有効であることを確認
        $this->assertArrayHasKey('recommended', $mathSuggestion);
        $this->assertArrayHasKey('recommended', $programmingSuggestion);
        $this->assertArrayHasKey('recommended', $generalSuggestion);
        
        // 信頼度が妥当な範囲内であることを確認
        foreach ([$mathSuggestion, $programmingSuggestion, $generalSuggestion] as $suggestion) {
            $confidence = $suggestion['recommended']['confidence'];
            $this->assertGreaterThanOrEqual(0, $confidence);
            $this->assertLessThanOrEqual(1, $confidence);
        }
    }

    // === ヘルパーメソッド ===

    /**
     * ポモドーロセッション列を作成
     */
    private function createPomodoroSequence(User $user, SubjectArea $subjectArea, Carbon $startTime, int $focusSessions): void
    {
        $currentTime = $startTime->copy();

        for ($i = 0; $i < $focusSessions; $i++) {
            // 集中セッション
            PomodoroSession::factory()->create([
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

            // 短い休憩（最後以外）
            if ($i < $focusSessions - 1) {
                PomodoroSession::factory()->create([
                    'user_id' => $user->id,
                    'subject_area_id' => null,
                    'session_type' => 'short_break',
                    'planned_duration' => 5,
                    'actual_duration' => 5,
                    'started_at' => $currentTime->copy(),
                    'completed_at' => $currentTime->copy()->addMinutes(5),
                    'is_completed' => true,
                    'was_interrupted' => false
                ]);

                $currentTime->addMinutes(5);
            }
        }
    }
}