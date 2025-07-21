<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\ExamType;
use App\Models\SubjectArea;
use App\Models\StudySession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudySessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->seed();
    }

    /** @test */
    public function study_session_has_correct_fillable_attributes()
    {
        $studySession = new StudySession();
        $expected = ['user_id', 'subject_area_id', 'started_at', 'ended_at', 'duration_minutes', 'study_comment'];
        
        $this->assertEquals($expected, $studySession->getFillable());
    }

    /** @test */
    public function study_session_casts_attributes_correctly()
    {
        $studySession = StudySession::factory()->create([
            'started_at' => '2024-01-01 10:00:00',
            'ended_at' => '2024-01-01 11:00:00',
            'duration_minutes' => '60',
        ]);
        
        $this->assertInstanceOf(Carbon::class, $studySession->started_at);
        $this->assertInstanceOf(Carbon::class, $studySession->ended_at);
        $this->assertIsInt($studySession->duration_minutes);
        $this->assertEquals(60, $studySession->duration_minutes);
    }

    /** @test */
    public function study_session_belongs_to_user()
    {
        $user = User::factory()->create();
        $studySession = StudySession::factory()->create(['user_id' => $user->id]);
        
        $this->assertInstanceOf(User::class, $studySession->user);
        $this->assertEquals($user->id, $studySession->user->id);
        $this->assertEquals($user->name, $studySession->user->name);
    }

    /** @test */
    public function study_session_belongs_to_subject_area()
    {
        $subjectArea = SubjectArea::factory()->create();
        $studySession = StudySession::factory()->create(['subject_area_id' => $subjectArea->id]);
        
        $this->assertInstanceOf(SubjectArea::class, $studySession->subjectArea);
        $this->assertEquals($subjectArea->id, $studySession->subjectArea->id);
        $this->assertEquals($subjectArea->name, $studySession->subjectArea->name);
    }

    /** @test */
    public function completed_scope_returns_only_completed_sessions()
    {
        $user = User::factory()->create();
        $subjectArea = SubjectArea::factory()->create();
        
        // 完了済みセッション
        StudySession::factory()->create([
            'user_id' => $user->id,
            'subject_area_id' => $subjectArea->id,
            'ended_at' => Carbon::now(),
            'duration_minutes' => 60,
        ]);
        
        // 進行中セッション
        StudySession::factory()->create([
            'user_id' => $user->id,
            'subject_area_id' => $subjectArea->id,
            'ended_at' => null,
            'duration_minutes' => 0,
        ]);
        
        $completedSessions = StudySession::completed()->get();
        
        $this->assertGreaterThan(0, $completedSessions->count());
        foreach ($completedSessions as $session) {
            $this->assertNotNull($session->ended_at);
            $this->assertGreaterThan(0, $session->duration_minutes);
        }
    }

    /** @test */
    public function active_scope_returns_only_active_sessions()
    {
        $user = User::factory()->create();
        $subjectArea = SubjectArea::factory()->create();
        
        // 進行中セッション
        StudySession::factory()->create([
            'user_id' => $user->id,
            'subject_area_id' => $subjectArea->id,
            'ended_at' => null,
        ]);
        
        // 完了済みセッション
        StudySession::factory()->create([
            'user_id' => $user->id,
            'subject_area_id' => $subjectArea->id,
            'ended_at' => Carbon::now(),
        ]);
        
        $activeSessions = StudySession::active()->get();
        
        $this->assertGreaterThan(0, $activeSessions->count());
        foreach ($activeSessions as $session) {
            $this->assertNull($session->ended_at);
        }
    }

    /** @test */
    public function date_range_scope_filters_by_date_range()
    {
        $user = User::factory()->create();
        $subjectArea = SubjectArea::factory()->create();
        
        $startDate = Carbon::now()->subDays(5);
        $endDate = Carbon::now()->subDays(1);
        
        // 範囲内のセッション
        StudySession::factory()->create([
            'user_id' => $user->id,
            'subject_area_id' => $subjectArea->id,
            'started_at' => $startDate->copy()->addDays(1),
            'ended_at' => $startDate->copy()->addDays(1)->addHours(1),
        ]);
        
        // 範囲外のセッション
        StudySession::factory()->create([
            'user_id' => $user->id,
            'subject_area_id' => $subjectArea->id,
            'started_at' => $startDate->copy()->subDays(1),
            'ended_at' => $startDate->copy()->subDays(1)->addHours(1),
        ]);
        
        $sessionsInRange = StudySession::dateRange($startDate, $endDate)->get();
        
        $this->assertEquals(1, $sessionsInRange->count());
        foreach ($sessionsInRange as $session) {
            $this->assertTrue($session->started_at->between($startDate, $endDate));
        }
    }

    /** @test */
    public function by_user_scope_filters_by_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $subjectArea = SubjectArea::factory()->create();
        
        StudySession::factory()->create(['user_id' => $user1->id, 'subject_area_id' => $subjectArea->id]);
        StudySession::factory()->create(['user_id' => $user2->id, 'subject_area_id' => $subjectArea->id]);
        
        $user1Sessions = StudySession::byUser($user1->id)->get();
        
        $this->assertGreaterThan(0, $user1Sessions->count());
        foreach ($user1Sessions as $session) {
            $this->assertEquals($user1->id, $session->user_id);
        }
    }

    /** @test */
    public function recent_scope_returns_limited_sessions_in_desc_order()
    {
        $user = User::factory()->create();
        $subjectArea = SubjectArea::factory()->create();
        
        // 複数のセッションを作成（異なる開始時刻）
        for ($i = 0; $i < 15; $i++) {
            StudySession::factory()->create([
                'user_id' => $user->id,
                'subject_area_id' => $subjectArea->id,
                'started_at' => Carbon::now()->subDays($i),
                'ended_at' => Carbon::now()->subDays($i)->addHours(1),
            ]);
        }
        
        $recentSessions = StudySession::recent(10)->get();
        
        $this->assertCount(10, $recentSessions);
        
        // 新しい順にソートされていることを確認
        for ($i = 1; $i < $recentSessions->count(); $i++) {
            $this->assertTrue(
                $recentSessions[$i-1]->started_at >= $recentSessions[$i]->started_at
            );
        }
    }

    /** @test */
    public function can_create_study_session_with_valid_data()
    {
        $user = User::factory()->create();
        $subjectArea = SubjectArea::factory()->create();
        
        $sessionData = [
            'user_id' => $user->id,
            'subject_area_id' => $subjectArea->id,
            'started_at' => Carbon::now(),
            'study_comment' => '今日のテストについて学習しました',
        ];
        
        $studySession = StudySession::create($sessionData);
        
        $this->assertDatabaseHas('study_sessions', [
            'user_id' => $sessionData['user_id'],
            'subject_area_id' => $sessionData['subject_area_id'],
            'study_comment' => $sessionData['study_comment'],
        ]);
        
        $this->assertEquals($sessionData['study_comment'], $studySession->study_comment);
    }
} 