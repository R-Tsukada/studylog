<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\StudySession;
use App\Models\PomodoroSession;
use App\Models\ExamType;
use App\Models\SubjectArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

class StudyAnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ExamType $examType;
    private SubjectArea $subjectArea;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->examType = ExamType::factory()->create(['name' => 'テスト試験']);
        $this->subjectArea = SubjectArea::factory()->create([
            'exam_type_id' => $this->examType->id,
            'name' => 'テスト分野'
        ]);
    }

    /**
     * @test
     */
    public function it_can_get_unified_history()
    {
        Sanctum::actingAs($this->user);

        // テストデータ作成
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHours(2),
            'ended_at' => Carbon::now()->subHours(1),
            'duration_minutes' => 60,
            'study_comment' => 'テスト学習'
        ]);

        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'actual_duration' => 25,
            'started_at' => Carbon::now()->subHours(1),
            'completed_at' => Carbon::now()->subMinutes(35),
            'is_completed' => true
        ]);

        $response = $this->getJson('/api/analytics/history');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'type',
                            'subject_area_id',
                            'subject_area_name',
                            'exam_type_name',
                            'duration_minutes',
                            'started_at',
                            'status',
                            'session_details'
                        ]
                    ],
                    'meta' => [
                        'total_count'
                    ]
                ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        
        // ポモドーロが最新（最初）
        $this->assertEquals('pomodoro', $data[0]['type']);
        $this->assertEquals('time_tracking', $data[1]['type']);
    }

    /**
     * @test
     */
    public function it_can_filter_history_by_date_range()
    {
        Sanctum::actingAs($this->user);

        // 範囲内のセッション
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subDays(2),
            'ended_at' => Carbon::now()->subDays(2)->addHour(),
            'duration_minutes' => 60
        ]);

        // 範囲外のセッション
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subDays(10),
            'ended_at' => Carbon::now()->subDays(10)->addHour(),
            'duration_minutes' => 60
        ]);

        $startDate = Carbon::now()->subDays(3)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        $response = $this->getJson("/api/analytics/history?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    /**
     * @test
     */
    public function it_validates_history_date_parameters()
    {
        Sanctum::actingAs($this->user);

        // 無効な日付形式
        $response = $this->getJson('/api/analytics/history?start_date=invalid-date');
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['start_date']);

        // 終了日が開始日より前
        $response = $this->getJson('/api/analytics/history?start_date=2024-01-10&end_date=2024-01-05');
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['end_date']);
    }

    /**
     * @test
     */
    public function it_can_get_unified_stats()
    {
        Sanctum::actingAs($this->user);

        // 時間計測セッション
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHours(2),
            'ended_at' => Carbon::now()->subHours(1),
            'duration_minutes' => 60
        ]);

        // ポモドーロセッション
        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'actual_duration' => 25,
            'started_at' => Carbon::now()->subHours(1),
            'completed_at' => Carbon::now()->subMinutes(35),
            'is_completed' => true
        ]);

        $response = $this->getJson('/api/analytics/stats');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'period' => ['start_date', 'end_date'],
                        'overview' => [
                            'total_study_time',
                            'total_sessions',
                            'average_session_length',
                            'study_days'
                        ],
                        'by_method' => [
                            'time_tracking' => [
                                'total_sessions',
                                'total_duration',
                                'average_duration',
                                'longest_session'
                            ],
                            'pomodoro' => [
                                'total_sessions',
                                'focus_sessions',
                                'total_focus_time',
                                'completion_rate',
                                'average_focus_duration'
                            ]
                        ],
                        'daily_breakdown',
                        'subject_breakdown',
                        'insights'
                    ]
                ]);

        $data = $response->json('data');
        $this->assertEquals(85, $data['overview']['total_study_time']); // 60 + 25
        $this->assertEquals(2, $data['overview']['total_sessions']);
        $this->assertEquals(1, $data['by_method']['time_tracking']['total_sessions']);
        $this->assertEquals(1, $data['by_method']['pomodoro']['focus_sessions']);
    }

    /**
     * @test
     */
    public function it_can_get_study_insights()
    {
        Sanctum::actingAs($this->user);

        // いくつかのセッションを作成
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subDays(5),
            'ended_at' => Carbon::now()->subDays(5)->addHours(2),
            'duration_minutes' => 120
        ]);

        $response = $this->getJson('/api/analytics/insights');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'preferred_method',
                        'best_study_times',
                        'productivity_trends',
                        'recommendations'
                    ]
                ]);

        $data = $response->json('data');
        $this->assertIsArray($data['best_study_times']);
        $this->assertIsArray($data['productivity_trends']);
        $this->assertIsArray($data['recommendations']);
    }

    /**
     * @test
     */
    public function it_can_suggest_study_method()
    {
        Sanctum::actingAs($this->user);

        // 長時間セッションを作成（時間計測推奨の条件）
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subDays(1),
            'ended_at' => Carbon::now()->subDays(1)->addHours(2),
            'duration_minutes' => 120
        ]);

        $response = $this->getJson('/api/analytics/suggest');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'recommended' => [
                            'method',
                            'confidence',
                            'reason'
                        ],
                        'alternatives' => [
                            '*' => [
                                'method',
                                'confidence',
                                'reason'
                            ]
                        ],
                        'context' => [
                            'time_of_day',
                            'recent_avg_duration',
                            'recent_method'
                        ]
                    ]
                ]);

        $data = $response->json('data');
        $this->assertContains($data['recommended']['method'], ['time_tracking', 'pomodoro']);
        $this->assertGreaterThanOrEqual(0, $data['recommended']['confidence']);
        $this->assertLessThanOrEqual(1, $data['recommended']['confidence']);
        $this->assertIsString($data['recommended']['reason']);
    }

    /**
     * @test
     */
    public function it_can_suggest_with_subject_area_filter()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/analytics/suggest?subject_area_id={$this->subjectArea->id}");

        $response->assertOk();
        
        $data = $response->json('data');
        $this->assertArrayHasKey('recommended', $data);
    }

    /**
     * @test
     */
    public function it_validates_subject_area_in_suggestion()
    {
        Sanctum::actingAs($this->user);

        // 存在しない学習分野ID
        $response = $this->getJson('/api/analytics/suggest?subject_area_id=99999');

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['subject_area_id']);
    }

    /**
     * @test
     */
    public function it_can_compare_periods()
    {
        Sanctum::actingAs($this->user);

        // 期間1のデータ
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subDays(5),
            'ended_at' => Carbon::now()->subDays(5)->addHour(),
            'duration_minutes' => 60
        ]);

        // 期間2のデータ
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subDays(15),
            'ended_at' => Carbon::now()->subDays(15)->addMinutes(30),
            'duration_minutes' => 30
        ]);

        $params = [
            'period1_start' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'period1_end' => Carbon::now()->subDays(3)->format('Y-m-d'),
            'period2_start' => Carbon::now()->subDays(20)->format('Y-m-d'),
            'period2_end' => Carbon::now()->subDays(10)->format('Y-m-d'),
        ];

        $response = $this->getJson('/api/analytics/comparison?' . http_build_query($params));

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'period1',
                        'period2',
                        'changes' => [
                            'total_study_time_change',
                            'session_count_change',
                            'average_session_change',
                            'study_days_change'
                        ],
                        'improvement_areas'
                    ]
                ]);

        $data = $response->json('data');
        $this->assertEquals(30, $data['changes']['total_study_time_change']); // 60 - 30
        $this->assertEquals(0, $data['changes']['session_count_change']); // 1 - 1
        $this->assertIsArray($data['improvement_areas']);
    }

    /**
     * @test
     */
    public function it_validates_comparison_parameters()
    {
        Sanctum::actingAs($this->user);

        // 必須パラメータ不足
        $response = $this->getJson('/api/analytics/comparison');
        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'period1_start', 'period1_end', 
                    'period2_start', 'period2_end'
                ]);

        // 期間1の終了日が開始日より前
        $params = [
            'period1_start' => '2024-01-10',
            'period1_end' => '2024-01-05',
            'period2_start' => '2024-01-01',
            'period2_end' => '2024-01-03',
        ];
        $response = $this->getJson('/api/analytics/comparison?' . http_build_query($params));
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['period1_end']);
    }

    /**
     * @test
     */
    public function it_requires_authentication()
    {
        // 認証なしでアクセス
        $response = $this->getJson('/api/analytics/history');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/analytics/stats');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/analytics/insights');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/analytics/suggest');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/analytics/comparison?period1_start=2024-01-01&period1_end=2024-01-02&period2_start=2024-01-03&period2_end=2024-01-04');
        $response->assertUnauthorized();
    }

    /**
     * @test
     */
    public function it_filters_data_by_user()
    {
        Sanctum::actingAs($this->user);

        // 他のユーザーのデータ
        $otherUser = User::factory()->create();
        StudySession::factory()->create([
            'user_id' => $otherUser->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHour(),
            'ended_at' => Carbon::now(),
            'duration_minutes' => 60
        ]);

        // 対象ユーザーのデータ
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHour(),
            'ended_at' => Carbon::now(),
            'duration_minutes' => 30
        ]);

        $response = $this->getJson('/api/analytics/history');
        $response->assertOk();
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(30, $data[0]['duration_minutes']);

        $response = $this->getJson('/api/analytics/stats');
        $response->assertOk();
        
        $stats = $response->json('data');
        $this->assertEquals(30, $stats['overview']['total_study_time']);
    }

    /**
     * @test
     */
    public function it_handles_empty_data_gracefully()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/analytics/history');
        $response->assertOk();
        $this->assertCount(0, $response->json('data'));

        $response = $this->getJson('/api/analytics/stats');
        $response->assertOk();
        $stats = $response->json('data');
        $this->assertEquals(0, $stats['overview']['total_study_time']);
        $this->assertEquals(0, $stats['overview']['total_sessions']);

        $response = $this->getJson('/api/analytics/insights');
        $response->assertOk();
        $this->assertIsArray($response->json('data'));

        $response = $this->getJson('/api/analytics/suggest');
        $response->assertOk();
        $this->assertArrayHasKey('recommended', $response->json('data'));
    }

    /**
     * @test
     */
    public function it_respects_limit_parameter()
    {
        Sanctum::actingAs($this->user);

        // 複数のセッションを作成
        for ($i = 0; $i < 5; $i++) {
            StudySession::factory()->create([
                'user_id' => $this->user->id,
                'subject_area_id' => $this->subjectArea->id,
                'started_at' => Carbon::now()->subHours($i + 1),
                'ended_at' => Carbon::now()->subHours($i),
                'duration_minutes' => 30
            ]);
        }

        $response = $this->getJson('/api/analytics/history?limit=3');
        $response->assertOk();
        
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    /**
     * @test
     */
    public function it_returns_error_on_exception()
    {
        Sanctum::actingAs($this->user);

        // 無効な日付で強制的にエラーを発生させる
        $response = $this->getJson('/api/analytics/stats?start_date=9999-99-99');
        
        // バリデーションエラーまたはサーバーエラーが返される
        $this->assertTrue($response->status() === 422 || $response->status() === 500);
    }
}