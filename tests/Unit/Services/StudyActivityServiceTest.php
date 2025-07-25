<?php

namespace Tests\Unit\Services;

use App\Models\ExamType;
use App\Models\PomodoroSession;
use App\Models\StudySession;
use App\Models\SubjectArea;
use App\Models\User;
use App\Services\StudyActivityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudyActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudyActivityService $service;

    private User $user;

    private ExamType $examType;

    private SubjectArea $subjectArea;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StudyActivityService;

        // テストユーザーとマスターデータを作成
        $this->user = User::factory()->create();
        $this->examType = ExamType::factory()->create(['name' => 'テスト試験']);
        $this->subjectArea = SubjectArea::factory()->create([
            'exam_type_id' => $this->examType->id,
            'name' => 'テスト分野',
        ]);
    }

    /**
     * @test
     */
    public function can_get_unified_history_with_both_session_types()
    {
        // 時間計測セッション作成
        $studySession = StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHours(2),
            'ended_at' => Carbon::now()->subHours(1),
            'duration_minutes' => 60,
            'study_comment' => 'テスト学習',
        ]);

        // ポモドーロセッション作成
        $pomodoroSession = PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'actual_duration' => 25,
            'started_at' => Carbon::now()->subHours(1),
            'completed_at' => Carbon::now()->subMinutes(35),
            'is_completed' => true,
            'was_interrupted' => false,
        ]);

        $history = $this->service->getUnifiedHistory($this->user->id);

        $this->assertCount(2, $history);

        // 時系列順でソートされているか確認（新しい順）
        $this->assertEquals('pomodoro', $history[0]['type']);
        $this->assertEquals('time_tracking', $history[1]['type']);

        // データ構造の確認
        $timeTrackingItem = $history[1];
        $this->assertEquals($studySession->id, $timeTrackingItem['id']);
        $this->assertEquals('time_tracking', $timeTrackingItem['type']);
        $this->assertEquals($this->subjectArea->name, $timeTrackingItem['subject_area_name']);
        $this->assertEquals($this->examType->name, $timeTrackingItem['exam_type_name']);
        $this->assertEquals(60, $timeTrackingItem['duration_minutes']);
        $this->assertEquals('completed', $timeTrackingItem['status']);
        $this->assertFalse($timeTrackingItem['was_interrupted']);

        $pomodoroItem = $history[0];
        $this->assertEquals($pomodoroSession->id, $pomodoroItem['id']);
        $this->assertEquals('pomodoro', $pomodoroItem['type']);
        $this->assertEquals($this->subjectArea->name, $pomodoroItem['subject_area_name']);
        $this->assertEquals(25, $pomodoroItem['duration_minutes']);
        $this->assertEquals('completed', $pomodoroItem['status']);
        $this->assertFalse($pomodoroItem['was_interrupted']);
        $this->assertEquals('focus', $pomodoroItem['session_details']['session_type']);
    }

    /**
     * @test
     */
    public function can_filter_history_by_date_range()
    {
        // 範囲内のセッション
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subDays(2),
            'ended_at' => Carbon::now()->subDays(2)->addHour(),
            'duration_minutes' => 60,
        ]);

        // 範囲外のセッション
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subDays(10),
            'ended_at' => Carbon::now()->subDays(10)->addHour(),
            'duration_minutes' => 60,
        ]);

        $startDate = Carbon::now()->subDays(3);
        $endDate = Carbon::now();

        $history = $this->service->getUnifiedHistory(
            $this->user->id,
            $startDate,
            $endDate
        );

        $this->assertCount(1, $history);
    }

    /**
     * @test
     */
    public function can_get_unified_stats()
    {
        // 時間計測セッション（複数）
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHours(3),
            'ended_at' => Carbon::now()->subHours(2),
            'duration_minutes' => 60,
        ]);

        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHours(2),
            'ended_at' => Carbon::now()->subHours(1),
            'duration_minutes' => 90,
        ]);

        // ポモドーロセッション（複数）
        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'actual_duration' => 25,
            'started_at' => Carbon::now()->subHours(1),
            'completed_at' => Carbon::now()->subMinutes(35),
            'is_completed' => true,
            'was_interrupted' => false,
        ]);

        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'actual_duration' => 20,
            'started_at' => Carbon::now()->subMinutes(30),
            'completed_at' => Carbon::now()->subMinutes(10),
            'is_completed' => true,
            'was_interrupted' => true,
        ]);

        $stats = $this->service->getUnifiedStats($this->user->id);

        // 概要統計の確認
        $this->assertEquals(195, $stats['overview']['total_study_time']); // 150 + 45
        $this->assertEquals(4, $stats['overview']['total_sessions']);
        $this->assertTrue(abs($stats['overview']['average_session_length'] - 48.75) < 0.1); // 平均値の許容範囲
        $this->assertTrue($stats['overview']['study_days'] >= 1);

        // 手法別統計の確認
        $this->assertEquals(2, $stats['by_method']['time_tracking']['total_sessions']);
        $this->assertEquals(150, $stats['by_method']['time_tracking']['total_duration']);
        $this->assertEquals(75, $stats['by_method']['time_tracking']['average_duration']);
        $this->assertEquals(90, $stats['by_method']['time_tracking']['longest_session']);

        $this->assertEquals(2, $stats['by_method']['pomodoro']['total_sessions']);
        $this->assertEquals(2, $stats['by_method']['pomodoro']['focus_sessions']);
        $this->assertEquals(45, $stats['by_method']['pomodoro']['total_focus_time']);
        $this->assertEquals(50.0, $stats['by_method']['pomodoro']['completion_rate']); // 1/2 = 50%
        $this->assertEquals(22.5, $stats['by_method']['pomodoro']['average_focus_duration']);

        // 学習分野別分析の確認
        $this->assertNotEmpty($stats['subject_breakdown']);
        $subjectStat = $stats['subject_breakdown'][0];
        $this->assertEquals($this->subjectArea->name, $subjectStat['subject_name']);
        $this->assertEquals(195, $subjectStat['total_duration']);
        $this->assertEquals(4, $subjectStat['session_count']);
        $this->assertEquals(150, $subjectStat['time_tracking_duration']);
        $this->assertEquals(45, $subjectStat['pomodoro_duration']);

        // 日別推移の確認
        $this->assertNotEmpty($stats['daily_breakdown']);
        $this->assertIsArray($stats['insights']);
    }

    /**
     * @test
     */
    public function can_get_study_insights()
    {
        // 最近のデータを作成
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subDays(5),
            'ended_at' => Carbon::now()->subDays(5)->addHours(2),
            'duration_minutes' => 120,
        ]);

        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'actual_duration' => 25,
            'started_at' => Carbon::now()->subDays(3),
            'completed_at' => Carbon::now()->subDays(3)->addMinutes(25),
            'is_completed' => true,
        ]);

        $insights = $this->service->getStudyInsights($this->user->id);

        $this->assertArrayHasKey('preferred_method', $insights);
        $this->assertArrayHasKey('best_study_times', $insights);
        $this->assertArrayHasKey('productivity_trends', $insights);
        $this->assertArrayHasKey('recommendations', $insights);

        // 好みの手法が判定されているか
        $this->assertContains($insights['preferred_method'], ['time_tracking', 'pomodoro', null]);

        // 学習時間分析があるか
        $this->assertIsArray($insights['best_study_times']);
        $this->assertArrayHasKey('morning', $insights['best_study_times']);

        // 生産性トレンドがあるか
        $this->assertArrayHasKey('trend', $insights['productivity_trends']);
        $this->assertContains($insights['productivity_trends']['trend'], ['improving', 'declining', 'stable']);

        // 推奨事項があるか
        $this->assertIsArray($insights['recommendations']);
    }

    /**
     * @test
     */
    public function can_suggest_study_method()
    {
        // 最近の長時間セッションを作成（時間計測推奨の条件）
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subDays(1),
            'ended_at' => Carbon::now()->subDays(1)->addHours(2),
            'duration_minutes' => 120,
        ]);

        $suggestion = $this->service->suggestStudyMethod($this->user->id, $this->subjectArea->id);

        $this->assertArrayHasKey('recommended', $suggestion);
        $this->assertArrayHasKey('alternatives', $suggestion);
        $this->assertArrayHasKey('context', $suggestion);

        // 推奨の構造確認
        $recommended = $suggestion['recommended'];
        $this->assertArrayHasKey('method', $recommended);
        $this->assertArrayHasKey('confidence', $recommended);
        $this->assertArrayHasKey('reason', $recommended);
        $this->assertContains($recommended['method'], ['time_tracking', 'pomodoro']);
        $this->assertGreaterThanOrEqual(0, $recommended['confidence']);
        $this->assertLessThanOrEqual(1, $recommended['confidence']);

        // コンテキスト情報の確認
        $context = $suggestion['context'];
        $this->assertArrayHasKey('time_of_day', $context);
        $this->assertArrayHasKey('recent_avg_duration', $context);
        $this->assertArrayHasKey('recent_method', $context);

        $this->assertIsInt($context['time_of_day']);
        $this->assertGreaterThanOrEqual(0, $context['time_of_day']);
        $this->assertLessThan(24, $context['time_of_day']);
    }

    /**
     * @test
     */
    public function suggests_time_tracking_for_long_sessions()
    {
        // 長時間セッションの履歴を複数作成
        for ($i = 0; $i < 3; $i++) {
            StudySession::factory()->create([
                'user_id' => $this->user->id,
                'subject_area_id' => $this->subjectArea->id,
                'started_at' => Carbon::now()->subDays($i + 1),
                'ended_at' => Carbon::now()->subDays($i + 1)->addHours(2),
                'duration_minutes' => 120,
            ]);
        }

        $suggestion = $this->service->suggestStudyMethod($this->user->id, $this->subjectArea->id);

        // 長時間セッションの履歴があるため、時間計測が推奨される可能性が高い
        $hasTimeTrackingSuggestion = $suggestion['recommended']['method'] === 'time_tracking' ||
            collect($suggestion['alternatives'])->contains('method', 'time_tracking');

        $this->assertTrue($hasTimeTrackingSuggestion);
    }

    /**
     * @test
     */
    public function filters_user_data_correctly()
    {
        // 他のユーザーのデータ
        $otherUser = User::factory()->create();
        StudySession::factory()->create([
            'user_id' => $otherUser->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHour(),
            'ended_at' => Carbon::now(),
            'duration_minutes' => 60,
        ]);

        // 対象ユーザーのデータ
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHour(),
            'ended_at' => Carbon::now(),
            'duration_minutes' => 30,
        ]);

        $history = $this->service->getUnifiedHistory($this->user->id);
        $stats = $this->service->getUnifiedStats($this->user->id);

        // 対象ユーザーのデータのみ取得されているか確認
        $this->assertCount(1, $history);
        $this->assertEquals(30, $history[0]['duration_minutes']);
        $this->assertEquals(30, $stats['overview']['total_study_time']);
    }

    /**
     * @test
     */
    public function handles_empty_data_gracefully()
    {
        $history = $this->service->getUnifiedHistory($this->user->id);
        $stats = $this->service->getUnifiedStats($this->user->id);
        $insights = $this->service->getStudyInsights($this->user->id);
        $suggestion = $this->service->suggestStudyMethod($this->user->id);

        // 空のデータでもエラーにならないか確認
        $this->assertCount(0, $history);
        $this->assertEquals(0, $stats['overview']['total_study_time']);
        $this->assertEquals(0, $stats['overview']['total_sessions']);
        $this->assertIsArray($insights);
        $this->assertArrayHasKey('recommended', $suggestion);
    }

    /**
     * @test
     */
    public function calculates_completion_rate_correctly()
    {
        // 完了セッション
        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'actual_duration' => 25,
            'is_completed' => true,
            'was_interrupted' => false,
        ]);

        // 中断セッション
        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'actual_duration' => 15,
            'is_completed' => true,
            'was_interrupted' => true,
        ]);

        $stats = $this->service->getUnifiedStats($this->user->id);

        $this->assertEquals(50.0, $stats['by_method']['pomodoro']['completion_rate']);
    }

    /**
     * @test
     */
    public function generates_appropriate_insights()
    {
        // 低い完了率のポモドーロセッション
        for ($i = 0; $i < 5; $i++) {
            PomodoroSession::factory()->create([
                'user_id' => $this->user->id,
                'subject_area_id' => $this->subjectArea->id,
                'session_type' => 'focus',
                'planned_duration' => 25,
                'actual_duration' => 15,
                'is_completed' => true,
                'was_interrupted' => true,
            ]);
        }

        $stats = $this->service->getUnifiedStats($this->user->id);

        $this->assertNotEmpty($stats['insights']);
        $hasCompletionRateInsight = collect($stats['insights'])
            ->contains(fn ($insight) => str_contains($insight, 'ポモドーロセッションの完了率'));

        $this->assertTrue($hasCompletionRateInsight);
    }

    /**
     * @test
     */
    public function can_get_grass_data_correctly()
    {
        // テストデータ作成
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // テストデータを直接作成
        \App\Models\DailyStudySummary::create([
            'user_id' => $this->user->id,
            'study_date' => $yesterday->format('Y-m-d'),
            'total_minutes' => 25,
            'session_count' => 1,
            'study_session_minutes' => 0,
            'pomodoro_minutes' => 25,
            'total_focus_sessions' => 1,
            'grass_level' => 1,
            'subject_breakdown' => ['テスト分野' => 25],
        ]);

        $result = $this->service->getGrassData(
            $this->user->id,
            $yesterday->format('Y-m-d'),
            $today->format('Y-m-d')
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('stats', $result);

        // 期間は2日間だから2日分のデータが返される
        $this->assertCount(2, $result['data']);

        // 統計の確認
        $stats = $result['stats'];

        // 統計の確認 - 実際は1日分（25分）のデータのみ取得される
        $this->assertEquals(1, $stats['studyDays']);
        $this->assertEquals(25, $stats['total_study_time']);
        $this->assertEquals(0.4, $stats['totalHours']); // 25分 = 0.4時間

        // データ構造の確認
        $this->assertIsArray($result['data']);
        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('stats', $result);
    }

    /**
     * @test
     */
    public function grass_level_calculation_is_correct()
    {
        $testCases = [
            [0, 0],    // 学習なし
            [30, 1],   // 30分 = レベル1
            [60, 1],   // 60分 = レベル1
            [90, 2],   // 90分 = レベル2
            [120, 2],  // 120分 = レベル2
            [150, 3],  // 150分 = レベル3
            [300, 3],   // 300分 = レベル3
        ];

        $model = new \App\Models\DailyStudySummary;

        foreach ($testCases as [$minutes, $expectedLevel]) {
            $actualLevel = $model->calculateGrassLevel($minutes);
            $this->assertEquals(
                $expectedLevel,
                $actualLevel,
                "失敗: {$minutes}分の場合、レベル{$expectedLevel}であるべきですが、{$actualLevel}でした"
            );
        }
    }

    /**
     * @test
     */
    public function can_build_grass_data_for_year()
    {
        $year = 2024;

        // 複数の日付でデータ作成
        $dates = [
            '2024-01-15' => 60,   // レベル1
            '2024-03-20' => 120,  // レベル2
            '2024-06-10' => 180,  // レベル3
            '2024-12-25' => 30,    // レベル1
        ];

        foreach ($dates as $date => $minutes) {
            $model = new \App\Models\DailyStudySummary;
            \App\Models\DailyStudySummary::create([
                'user_id' => $this->user->id,
                'study_date' => $date,
                'total_minutes' => $minutes,
                'study_session_minutes' => $minutes,
                'pomodoro_minutes' => 0,
                'session_count' => 1,
                'total_focus_sessions' => 0,
                'grass_level' => $model->calculateGrassLevel($minutes),
                'subject_breakdown' => [],
            ]);
        }

        $result = $this->service->getGrassData(
            $this->user->id,
            "{$year}-01-01",
            "{$year}-12-31"
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('stats', $result);

        // 2024年の1年間（366日）のデータが返される
        $this->assertCount(366, $result['data']);

        // 統計データの確認 - 学習した日は4日、合計390分
        $stats = $result['stats'];
        $this->assertEquals(4, $stats['studyDays']);
        $this->assertEquals(390, $stats['total_study_time']); // 総分数
        $this->assertEquals(6.5, $stats['totalHours']); // 390分 = 6.5時間
    }
}
