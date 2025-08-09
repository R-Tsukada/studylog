<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\DashboardController;
use App\Models\ExamType;
use App\Models\PomodoroSession;
use App\Models\StudyGoal;
use App\Models\StudySession;
use App\Models\SubjectArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAchievementRateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ExamType $examType;

    private SubjectArea $subjectArea;

    private StudyGoal $studyGoal;

    private DashboardController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用ユーザーを作成
        $this->user = User::factory()->create();

        // テスト用試験タイプを作成
        $this->examType = ExamType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'テスト試験',
        ]);

        // テスト用学習分野を作成
        $this->subjectArea = SubjectArea::factory()->create([
            'exam_type_id' => $this->examType->id,
            'name' => 'テスト分野',
        ]);

        // コントローラーインスタンスを作成
        $this->controller = new DashboardController;
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     * 目標時間と学習セッションのみで正確な達成率が計算されること
     */
    #[Test]
    public function 学習セッションのみで正確な目標達成率が計算されること()
    {
        // 目標を120分（2時間）に設定
        $this->studyGoal = StudyGoal::factory()->create([
            'user_id' => $this->user->id,
            'exam_type_id' => $this->examType->id,
            'daily_minutes_goal' => 120,
            'is_active' => true,
        ]);

        // 今日の学習セッションを80分作成
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => now(),
            'ended_at' => now()->addMinutes(80),
            'duration_minutes' => 80,
        ]);

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // 80分 ÷ 120分 = 66.67% → 67%（四捨五入）
        $this->assertEquals(67, $data['achievement_rate']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     * 目標時間とポモドーロセッションのみで正確な達成率が計算されること
     */
    #[Test]
    public function ポモドーロセッションのみで正確な目標達成率が計算されること()
    {
        // 目標を90分に設定
        $this->studyGoal = StudyGoal::factory()->create([
            'user_id' => $this->user->id,
            'exam_type_id' => $this->examType->id,
            'daily_minutes_goal' => 90,
            'is_active' => true,
        ]);

        // 今日のポモドーロセッション（focus）を45分作成
        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'actual_duration' => 45,
            'is_completed' => true,
            'started_at' => now(),
        ]);

        // break セッションも作成（これは計算に含まれない）
        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'session_type' => 'short_break',
            'planned_duration' => 5,
            'actual_duration' => 5,
            'is_completed' => true,
            'started_at' => now(),
        ]);

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // 45分 ÷ 90分 = 50%
        $this->assertEquals(50, $data['achievement_rate']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     * 目標時間と学習セッション・ポモドーロ両方で正確な合計達成率が計算されること
     */
    #[Test]
    public function 学習セッションとポモドーロ両方で正確な合計達成率が計算されること()
    {
        // 目標を150分（2.5時間）に設定
        $this->studyGoal = StudyGoal::factory()->create([
            'user_id' => $this->user->id,
            'exam_type_id' => $this->examType->id,
            'daily_minutes_goal' => 150,
            'is_active' => true,
        ]);

        // 今日の学習セッションを60分作成
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => now(),
            'ended_at' => now()->addMinutes(60),
            'duration_minutes' => 60,
        ]);

        // 今日のポモドーロセッション（focus）を75分作成
        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'actual_duration' => 75,
            'is_completed' => true,
            'started_at' => now(),
        ]);

        // 未完了のポモドーロセッション（計算に含まれない）
        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'actual_duration' => 0,
            'is_completed' => false,
            'started_at' => now(),
        ]);

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // 学習セッション60分 + ポモドーロ75分 = 135分合計
        // 135分 ÷ 150分 = 90%
        $this->assertEquals(90, $data['achievement_rate']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     * 目標達成率が100%を超える場合は100%にキャップされること
     */
    #[Test]
    public function 目標達成率が100パーセントを超える場合は100パーセントにキャップされること()
    {
        // 目標を60分に設定
        $this->studyGoal = StudyGoal::factory()->create([
            'user_id' => $this->user->id,
            'exam_type_id' => $this->examType->id,
            'daily_minutes_goal' => 60,
            'is_active' => true,
        ]);

        // 今日の学習セッションを100分作成（目標を超過）
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => now(),
            'ended_at' => now()->addMinutes(100),
            'duration_minutes' => 100,
        ]);

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // 100分 ÷ 60分 = 166.67% → 100%（上限）
        $this->assertEquals(100, $data['achievement_rate']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     * アクティブな目標が設定されていない場合は0%が返ること
     */
    #[Test]
    public function アクティブな目標が設定されていない場合は0パーセントが返ること()
    {
        // 非アクティブな目標を作成
        StudyGoal::factory()->create([
            'user_id' => $this->user->id,
            'exam_type_id' => $this->examType->id,
            'daily_minutes_goal' => 120,
            'is_active' => false, // 非アクティブ
        ]);

        // 今日の学習セッションを作成
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => now(),
            'ended_at' => now()->addMinutes(60),
            'duration_minutes' => 60,
        ]);

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // アクティブな目標がないため0%
        $this->assertEquals(0, $data['achievement_rate']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     * 目標時間が0の場合は0%が返ること
     */
    #[Test]
    public function 目標時間が0の場合は0パーセントが返ること()
    {
        // 目標を0分に設定
        $this->studyGoal = StudyGoal::factory()->create([
            'user_id' => $this->user->id,
            'exam_type_id' => $this->examType->id,
            'daily_minutes_goal' => 0,
            'is_active' => true,
        ]);

        // 今日の学習セッションを作成
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => now(),
            'ended_at' => now()->addMinutes(30),
            'duration_minutes' => 30,
        ]);

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // 目標が0分のため0%
        $this->assertEquals(0, $data['achievement_rate']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     * 昨日の学習データは計算に含まれないこと
     */
    #[Test]
    public function 昨日の学習データは計算に含まれないこと()
    {
        // 目標を60分に設定
        $this->studyGoal = StudyGoal::factory()->create([
            'user_id' => $this->user->id,
            'exam_type_id' => $this->examType->id,
            'daily_minutes_goal' => 60,
            'is_active' => true,
        ]);

        // 昨日の学習セッションを作成
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => now()->subDay(),
            'ended_at' => now()->subDay()->addMinutes(100),
            'duration_minutes' => 100,
        ]);

        // 昨日のポモドーロセッションを作成
        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'actual_duration' => 50,
            'is_completed' => true,
            'started_at' => now()->subDay(),
        ]);

        // 今日の学習セッションを30分作成
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => now(),
            'ended_at' => now()->addMinutes(30),
            'duration_minutes' => 30,
        ]);

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // 今日のデータのみ：30分 ÷ 60分 = 50%
        $this->assertEquals(50, $data['achievement_rate']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     * 他のユーザーの学習データは計算に含まれないこと
     */
    #[Test]
    public function 他のユーザーの学習データは計算に含まれないこと()
    {
        // 他のユーザーを作成
        $otherUser = User::factory()->create();
        $otherExamType = ExamType::factory()->create(['user_id' => $otherUser->id]);
        $otherSubjectArea = SubjectArea::factory()->create(['exam_type_id' => $otherExamType->id]);

        // 目標を60分に設定
        $this->studyGoal = StudyGoal::factory()->create([
            'user_id' => $this->user->id,
            'exam_type_id' => $this->examType->id,
            'daily_minutes_goal' => 60,
            'is_active' => true,
        ]);

        // 他のユーザーの学習データを作成
        StudySession::factory()->create([
            'user_id' => $otherUser->id,
            'subject_area_id' => $otherSubjectArea->id,
            'started_at' => now(),
            'ended_at' => now()->addMinutes(100),
            'duration_minutes' => 100,
        ]);

        PomodoroSession::factory()->create([
            'user_id' => $otherUser->id,
            'session_type' => 'focus',
            'planned_duration' => 25,
            'actual_duration' => 50,
            'is_completed' => true,
            'started_at' => now(),
        ]);

        // 自分の学習セッションを15分作成
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => now(),
            'ended_at' => now()->addMinutes(15),
            'duration_minutes' => 15,
        ]);

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // 自分のデータのみ：15分 ÷ 60分 = 25%
        $this->assertEquals(25, $data['achievement_rate']);
    }
}
