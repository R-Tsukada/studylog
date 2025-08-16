<?php

namespace Tests\Unit\Models;

use App\Models\PomodoroSession;
use App\Models\StudySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PomodoroSessionTest extends TestCase
{
    use RefreshDatabase;

    

/**
     * テストメソッド
     */
    #[Test]
    public function pomodoro_session_has_correct_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'study_session_id',
            'subject_area_id',
            'session_type',
            'planned_duration',
            'actual_duration',
            'started_at',
            'completed_at',
            'is_completed',
            'was_interrupted',
            'settings',
            'notes',
        ];

        $pomodoroSession = new PomodoroSession;
        $this->assertEquals($fillable, $pomodoroSession->getFillable());
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function pomodoro_session_casts_attributes_correctly()
    {
        $casts = [
            'id' => 'int',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_completed' => 'boolean',
            'was_interrupted' => 'boolean',
            'settings' => 'array',
        ];

        $pomodoroSession = new PomodoroSession;
        foreach ($casts as $attribute => $expectedCast) {
            $this->assertEquals($expectedCast, $pomodoroSession->getCasts()[$attribute] ?? null);
        }
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function pomodoro_session_belongs_to_user()
    {
        $user = User::factory()->create();
        $pomodoroSession = PomodoroSession::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $pomodoroSession->user);
        $this->assertEquals($user->id, $pomodoroSession->user->id);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function pomodoro_session_belongs_to_study_session()
    {
        $user = User::factory()->create();
        $studySession = StudySession::factory()->create(['user_id' => $user->id]);
        $pomodoroSession = PomodoroSession::factory()->create([
            'user_id' => $user->id,
            'study_session_id' => $studySession->id,
        ]);

        $this->assertInstanceOf(StudySession::class, $pomodoroSession->studySession);
        $this->assertEquals($studySession->id, $pomodoroSession->studySession->id);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function by_user_scope_filters_by_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $session1 = PomodoroSession::factory()->create(['user_id' => $user1->id]);
        $session2 = PomodoroSession::factory()->create(['user_id' => $user2->id]);

        $user1Sessions = PomodoroSession::byUser($user1->id)->get();

        $this->assertCount(1, $user1Sessions);
        $this->assertEquals($session1->id, $user1Sessions->first()->id);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function completed_scope_returns_only_completed_sessions()
    {
        $user = User::factory()->create();

        $completedSession = PomodoroSession::factory()->completed()->create(['user_id' => $user->id]);
        $activeSession = PomodoroSession::factory()->active()->create(['user_id' => $user->id]);

        $completedSessions = PomodoroSession::completed()->get();

        $this->assertCount(1, $completedSessions);
        $this->assertEquals($completedSession->id, $completedSessions->first()->id);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function focus_sessions_scope_returns_only_focus_sessions()
    {
        $user = User::factory()->create();

        $focusSession = PomodoroSession::factory()->focus()->create(['user_id' => $user->id]);
        $breakSession = PomodoroSession::factory()->shortBreak()->create(['user_id' => $user->id]);

        $focusSessions = PomodoroSession::focusSessions()->get();

        $this->assertCount(1, $focusSessions);
        $this->assertEquals($focusSession->id, $focusSessions->first()->id);
        $this->assertEquals('focus', $focusSessions->first()->session_type);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function break_sessions_scope_returns_only_break_sessions()
    {
        $user = User::factory()->create();

        $focusSession = PomodoroSession::factory()->focus()->create(['user_id' => $user->id]);
        $shortBreakSession = PomodoroSession::factory()->shortBreak()->create(['user_id' => $user->id]);
        $longBreakSession = PomodoroSession::factory()->longBreak()->create(['user_id' => $user->id]);

        $breakSessions = PomodoroSession::breakSessions()->get();

        $this->assertCount(2, $breakSessions);
        $this->assertContains('short_break', $breakSessions->pluck('session_type')->toArray());
        $this->assertContains('long_break', $breakSessions->pluck('session_type')->toArray());
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function date_range_scope_filters_by_date_range()
    {
        $user = User::factory()->create();

        $todaySession = PomodoroSession::factory()->create([
            'user_id' => $user->id,
            'started_at' => now(),
        ]);

        $yesterdaySession = PomodoroSession::factory()->create([
            'user_id' => $user->id,
            'started_at' => now()->subDay(),
        ]);

        $todaySessions = PomodoroSession::dateRange(
            now()->startOfDay(),
            now()->endOfDay()
        )->get();

        $this->assertCount(1, $todaySessions);
        $this->assertEquals($todaySession->id, $todaySessions->first()->id);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function today_scope_returns_todays_sessions()
    {
        $user = User::factory()->create();

        $todaySession = PomodoroSession::factory()->create([
            'user_id' => $user->id,
            'started_at' => now(),
        ]);

        $yesterdaySession = PomodoroSession::factory()->create([
            'user_id' => $user->id,
            'started_at' => now()->subDay(),
        ]);

        $todaySessions = PomodoroSession::today()->get();

        $this->assertCount(1, $todaySessions);
        $this->assertEquals($todaySession->id, $todaySessions->first()->id);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function recent_scope_returns_limited_sessions_in_desc_order()
    {
        $user = User::factory()->create();

        $sessions = collect();
        for ($i = 0; $i < 5; $i++) {
            $sessions->push(PomodoroSession::factory()->create([
                'user_id' => $user->id,
                'started_at' => now()->subMinutes($i * 10),
            ]));
        }

        $recentSessions = PomodoroSession::recent(3)->get();

        $this->assertCount(3, $recentSessions);

        // 最新順に並んでいることを確認
        $this->assertEquals($sessions->first()->id, $recentSessions->first()->id);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function can_create_pomodoro_session_with_valid_data()
    {
        $user = User::factory()->create();

        $pomodoroSessionData = [
            'user_id' => $user->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'started_at' => now(),
            'is_completed' => false,
            'was_interrupted' => false,
            'settings' => [
                'focus_duration' => 25,
                'short_break_duration' => 5,
                'sound_enabled' => true,
            ],
        ];

        $pomodoroSession = PomodoroSession::create($pomodoroSessionData);

        $this->assertInstanceOf(PomodoroSession::class, $pomodoroSession);
        $this->assertEquals($user->id, $pomodoroSession->user_id);
        $this->assertEquals('focus', $pomodoroSession->session_type);
        $this->assertEquals(25, $pomodoroSession->planned_duration);
        $this->assertFalse($pomodoroSession->is_completed);
        $this->assertFalse($pomodoroSession->was_interrupted);
        $this->assertIsArray($pomodoroSession->settings);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function duration_in_minutes_accessor_returns_actual_or_planned_duration()
    {
        $pomodoroSession = PomodoroSession::factory()->make([
            'planned_duration' => 25,
            'actual_duration' => null,
        ]);

        $this->assertEquals(25, $pomodoroSession->duration_in_minutes);

        $pomodoroSession->actual_duration = 23;
        $this->assertEquals(23, $pomodoroSession->duration_in_minutes);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function is_active_accessor_returns_correct_status()
    {
        $activeSession = PomodoroSession::factory()->active()->make();
        $completedSession = PomodoroSession::factory()->completed()->make();

        $this->assertTrue($activeSession->is_active);
        $this->assertFalse($completedSession->is_active);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function completion_percentage_accessor_calculates_correctly()
    {
        $pomodoroSession = PomodoroSession::factory()->make([
            'planned_duration' => 25,
            'actual_duration' => 20,
        ]);

        $this->assertEquals(80, $pomodoroSession->completion_percentage);

        // 計画時間を超えた場合は100%上限
        $pomodoroSession->actual_duration = 30;
        $this->assertEquals(100, $pomodoroSession->completion_percentage);

        // actual_durationがnullの場合は0%
        $pomodoroSession->actual_duration = null;
        $this->assertEquals(0, $pomodoroSession->completion_percentage);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function can_create_different_session_types()
    {
        $user = User::factory()->create();

        $focusSession = PomodoroSession::factory()->focus()->create(['user_id' => $user->id]);
        $shortBreakSession = PomodoroSession::factory()->shortBreak()->create(['user_id' => $user->id]);
        $longBreakSession = PomodoroSession::factory()->longBreak()->create(['user_id' => $user->id]);

        $this->assertEquals('focus', $focusSession->session_type);
        $this->assertEquals('short_break', $shortBreakSession->session_type);
        $this->assertEquals('long_break', $longBreakSession->session_type);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function can_link_to_study_session()
    {
        $user = User::factory()->create();
        $studySession = StudySession::factory()->create(['user_id' => $user->id]);

        $pomodoroSession = PomodoroSession::factory()->create([
            'user_id' => $user->id,
            'study_session_id' => $studySession->id,
        ]);

        $this->assertEquals($studySession->id, $pomodoroSession->study_session_id);
        $this->assertInstanceOf(StudySession::class, $pomodoroSession->studySession);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function study_session_link_can_be_null()
    {
        $user = User::factory()->create();

        $pomodoroSession = PomodoroSession::factory()->create([
            'user_id' => $user->id,
            'study_session_id' => null,
        ]);

        $this->assertNull($pomodoroSession->study_session_id);
        $this->assertNull($pomodoroSession->studySession);
    }
}
