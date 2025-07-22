<?php

namespace Tests\Feature\Api;

use App\Models\PomodoroSession;
use App\Models\User;
use App\Models\StudySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PomodoroControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * @test
     */
    public function it_can_get_pomodoro_sessions_list()
    {
        $sessions = PomodoroSession::factory()->count(3)->create(['user_id' => $this->user->id]);
        $otherUser = User::factory()->create();
        $otherUserSession = PomodoroSession::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/pomodoro');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'session_type',
                        'planned_duration',
                        'actual_duration',
                        'started_at',
                        'completed_at',
                        'is_completed',
                        'was_interrupted'
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /**
     * @test
     */
    public function it_can_filter_sessions_by_date()
    {
        $todaySession = PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'started_at' => now()
        ]);
        
        $yesterdaySession = PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'started_at' => now()->subDay()
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/pomodoro?date=' . now()->toDateString());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    /**
     * @test
     */
    public function it_can_filter_sessions_by_type()
    {
        $focusSession = PomodoroSession::factory()->focus()->create(['user_id' => $this->user->id]);
        $breakSession = PomodoroSession::factory()->shortBreak()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/pomodoro?session_type=focus');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('focus', $response->json('data.0.session_type'));
    }

    /**
     * @test
     */
    public function it_can_create_pomodoro_session()
    {
        $sessionData = [
            'session_type' => 'focus',
            'planned_duration' => 25,
            'settings' => [
                'focus_duration' => 25,
                'short_break_duration' => 5,
                'sound_enabled' => true
            ]
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/pomodoro', $sessionData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'user_id',
                'session_type',
                'planned_duration',
                'started_at',
                'settings'
            ]);

        $this->assertDatabaseHas('pomodoro_sessions', [
            'user_id' => $this->user->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'is_completed' => false
        ]);
    }

    /**
     * @test
     */
    public function it_can_create_pomodoro_session_with_study_session_link()
    {
        $studySession = StudySession::factory()->create(['user_id' => $this->user->id]);

        $sessionData = [
            'session_type' => 'focus',
            'planned_duration' => 25,
            'study_session_id' => $studySession->id
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/pomodoro', $sessionData);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('pomodoro_sessions', [
            'user_id' => $this->user->id,
            'study_session_id' => $studySession->id
        ]);
    }

    /**
     * @test
     */
    public function it_validates_pomodoro_session_creation()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/pomodoro', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_type', 'planned_duration']);
    }

    /**
     * @test
     */
    public function it_prevents_multiple_active_sessions()
    {
        $activeSession = PomodoroSession::factory()->active()->create(['user_id' => $this->user->id]);

        $sessionData = [
            'session_type' => 'focus',
            'planned_duration' => 25
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/pomodoro', $sessionData);

        $response->assertStatus(409)
            ->assertJson([
                'message' => '既にアクティブなポモドーロセッションがあります。'
            ]);
    }

    /**
     * @test
     */
    public function it_can_show_specific_pomodoro_session()
    {
        $session = PomodoroSession::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/pomodoro/{$session->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $session->id,
                'user_id' => $this->user->id
            ]);
    }

    /**
     * @test
     */
    public function it_cannot_show_other_users_session()
    {
        $otherUser = User::factory()->create();
        $session = PomodoroSession::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/pomodoro/{$session->id}");

        $response->assertStatus(403);
    }

    /**
     * @test
     */
    public function it_can_update_pomodoro_session()
    {
        $session = PomodoroSession::factory()->active()->create(['user_id' => $this->user->id]);

        $updateData = [
            'actual_duration' => 25,
            'notes' => 'Great focus session!'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/pomodoro/{$session->id}", $updateData);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('pomodoro_sessions', [
            'id' => $session->id,
            'actual_duration' => 25,
            'notes' => 'Great focus session!'
        ]);
    }

    /**
     * @test
     */
    public function it_can_complete_pomodoro_session()
    {
        $session = PomodoroSession::factory()->active()->create(['user_id' => $this->user->id]);

        $completeData = [
            'actual_duration' => 25,
            'was_interrupted' => false,
            'notes' => 'Completed successfully!'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/pomodoro/{$session->id}/complete", $completeData);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('pomodoro_sessions', [
            'id' => $session->id,
            'actual_duration' => 25,
            'is_completed' => true,
            'was_interrupted' => false,
            'notes' => 'Completed successfully!'
        ]);
    }

    /**
     * @test
     */
    public function it_cannot_complete_already_completed_session()
    {
        $session = PomodoroSession::factory()->completed()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/pomodoro/{$session->id}/complete", [
                'actual_duration' => 25
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'このセッションは既に完了しています。'
            ]);
    }

    /**
     * @test
     */
    public function it_can_get_current_active_session()
    {
        $activeSession = PomodoroSession::factory()->active()->create(['user_id' => $this->user->id]);
        $completedSession = PomodoroSession::factory()->completed()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/pomodoro/current');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $activeSession->id,
                'is_completed' => false
            ]);
    }

    /**
     * @test
     */
    public function it_returns_404_when_no_current_session()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/pomodoro/current');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'アクティブなポモドーロセッションがありません。'
            ]);
    }

    /**
     * @test
     */
    public function it_can_get_pomodoro_statistics()
    {
        // 今日のセッションを作成
        $todayFocusSession = PomodoroSession::factory()->focus()->completed()->create([
            'user_id' => $this->user->id,
            'started_at' => now(),
            'actual_duration' => 25
        ]);

        $todayBreakSession = PomodoroSession::factory()->shortBreak()->completed()->create([
            'user_id' => $this->user->id,
            'started_at' => now(),
            'actual_duration' => 5
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/pomodoro/stats?start_date=' . now()->toDateString() . '&end_date=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => [
                    'total_sessions',
                    'focus_sessions',
                    'break_sessions',
                    'total_focus_time',
                    'total_break_time',
                    'interrupted_sessions',
                    'completion_rate',
                    'average_focus_duration'
                ],
                'daily_stats'
            ]);

        $stats = $response->json('stats');
        $this->assertEquals(2, $stats['total_sessions']);
        $this->assertEquals(1, $stats['focus_sessions']);
        $this->assertEquals(1, $stats['break_sessions']);
        $this->assertEquals(25, $stats['total_focus_time']);
        $this->assertEquals(5, $stats['total_break_time']);
    }

    /**
     * @test
     */
    public function it_returns_zero_stats_when_no_sessions()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/pomodoro/stats');

        $response->assertStatus(200);
        
        $stats = $response->json('stats');
        $this->assertEquals(0, $stats['total_sessions']);
        $this->assertEquals(0, $stats['total_focus_time']);
        $this->assertEquals(0, $stats['completion_rate']);
    }

    /**
     * @test
     */
    public function it_can_delete_pomodoro_session()
    {
        $session = PomodoroSession::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/pomodoro/{$session->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'ポモドーロセッションが削除されました。'
            ]);

        $this->assertDatabaseMissing('pomodoro_sessions', [
            'id' => $session->id
        ]);
    }

    /**
     * @test
     */
    public function it_cannot_delete_other_users_session()
    {
        $otherUser = User::factory()->create();
        $session = PomodoroSession::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/pomodoro/{$session->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('pomodoro_sessions', [
            'id' => $session->id
        ]);
    }

    /**
     * @test
     */
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/pomodoro');
        $response->assertStatus(401);

        $response = $this->postJson('/api/pomodoro', []);
        $response->assertStatus(401);

        $response = $this->getJson('/api/pomodoro/current');
        $response->assertStatus(401);

        $response = $this->getJson('/api/pomodoro/stats');
        $response->assertStatus(401);
    }

    /**
     * @test
     */
    public function it_validates_session_type()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/pomodoro', [
                'session_type' => 'invalid_type',
                'planned_duration' => 25
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_type']);
    }

    /**
     * @test
     */
    public function it_validates_planned_duration()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/pomodoro', [
                'session_type' => 'focus',
                'planned_duration' => 0
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['planned_duration']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/pomodoro', [
                'session_type' => 'focus',
                'planned_duration' => 200
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['planned_duration']);
    }

    /**
     * @test
     */
    public function it_calculates_completion_rate_correctly()
    {
        // 完了セッション（中断なし）
        PomodoroSession::factory()->count(8)->completed()->create([
            'user_id' => $this->user->id,
            'was_interrupted' => false,
            'started_at' => now()
        ]);

        // 中断セッション
        PomodoroSession::factory()->count(2)->interrupted()->create([
            'user_id' => $this->user->id,
            'was_interrupted' => true,
            'started_at' => now()
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/pomodoro/stats?start_date=' . now()->toDateString() . '&end_date=' . now()->toDateString());

        $response->assertStatus(200);
        
        $stats = $response->json('stats');
        $this->assertEquals(10, $stats['total_sessions']);
        $this->assertEquals(2, $stats['interrupted_sessions']);
        $this->assertEquals(80.0, $stats['completion_rate']); // (10-2)/10 * 100 = 80%
    }

    /**
     * @test
     */
    public function it_filters_sessions_by_completion_status()
    {
        $completedSession = PomodoroSession::factory()->completed()->create(['user_id' => $this->user->id]);
        $activeSession = PomodoroSession::factory()->active()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/pomodoro?is_completed=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_completed'));

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/pomodoro?is_completed=false');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertFalse($response->json('data.0.is_completed'));
    }
}