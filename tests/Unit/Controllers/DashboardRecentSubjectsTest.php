<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\DashboardController;
use App\Models\ExamType;
use App\Models\PomodoroSession;
use App\Models\StudySession;
use App\Models\SubjectArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRecentSubjectsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ExamType $examType;

    private SubjectArea $subjectArea;

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
     * 学習セッションのみが存在する場合の最近の学習履歴取得
     */
    #[Test]
    public function 学習セッションのみの場合の最近の学習履歴が正しく取得されること()
    {
        // 学習セッションを3つ作成
        $sessions = [];
        for ($i = 0; $i < 3; $i++) {
            $sessions[] = StudySession::factory()->create([
                'user_id' => $this->user->id,
                'subject_area_id' => $this->subjectArea->id,
                'started_at' => now()->subMinutes($i * 30),
                'ended_at' => now()->subMinutes($i * 30)->addMinutes(25),
                'duration_minutes' => 25,
                'study_comment' => "学習セッション{$i}のメモ",
            ]);
        }

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // recent_subjectsが存在し、3件の学習セッションが含まれることを確認
        $this->assertArrayHasKey('recent_subjects', $data);
        $this->assertCount(3, $data['recent_subjects']);

        // 最新のセッションが最初に表示されることを確認
        $firstSession = $data['recent_subjects'][0];
        $this->assertEquals('study_session', $firstSession['type']);
        $this->assertEquals($sessions[0]->id, $firstSession['id']);
        $this->assertEquals('テスト分野', $firstSession['subject_area_name']);
        $this->assertEquals('テスト試験', $firstSession['exam_type_name']);
        $this->assertEquals(25, $firstSession['duration_minutes']);
        $this->assertEquals('学習セッション0のメモ', $firstSession['notes']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     * ポモドーロセッションのみが存在する場合の最近の学習履歴取得
     */
    #[Test]
    public function ポモドーロセッションのみの場合の最近の学習履歴が正しく取得されること()
    {
        // ポモドーロセッションを2つ作成
        $sessions = [];
        for ($i = 0; $i < 2; $i++) {
            $sessions[] = PomodoroSession::factory()->create([
                'user_id' => $this->user->id,
                'subject_area_id' => $this->subjectArea->id,
                'session_type' => 'focus',
                'actual_duration' => 25,
                'is_completed' => true,
                'started_at' => now()->subMinutes($i * 30),
                'notes' => "ポモドーロ{$i}のメモ",
            ]);
        }

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // recent_subjectsが存在し、2件のポモドーロセッションが含まれることを確認
        $this->assertArrayHasKey('recent_subjects', $data);
        $this->assertCount(2, $data['recent_subjects']);

        // 最新のセッションが最初に表示されることを確認
        $firstSession = $data['recent_subjects'][0];
        $this->assertEquals('pomodoro_session', $firstSession['type']);
        $this->assertEquals($sessions[0]->id, $firstSession['id']);
        $this->assertEquals('テスト分野', $firstSession['subject_area_name']);
        $this->assertEquals('テスト試験', $firstSession['exam_type_name']);
        $this->assertEquals(25, $firstSession['duration_minutes']);
        $this->assertEquals('ポモドーロ0のメモ', $firstSession['notes']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     * 学習セッションとポモドーロセッションが混在する場合の時系列順表示
     */
    #[Test]
    public function 学習セッションとポモドーロセッションが時系列順で正しく表示されること()
    {
        // 時系列データを作成（新しい順）
        $pomodoroSession = PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'session_type' => 'focus',
            'actual_duration' => 25,
            'is_completed' => true,
            'started_at' => now(),
            'notes' => '最新のポモドーロメモ',
        ]);

        $studySession = StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => now()->subMinutes(30),
            'ended_at' => now()->subMinutes(30)->addMinutes(45),
            'duration_minutes' => 45,
            'study_comment' => '古い学習セッションメモ',
        ]);

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // recent_subjectsが存在し、2件のセッションが含まれることを確認
        $this->assertArrayHasKey('recent_subjects', $data);
        $this->assertCount(2, $data['recent_subjects']);

        // 時系列順（新しい順）で表示されることを確認
        $this->assertEquals('pomodoro_session', $data['recent_subjects'][0]['type']);
        $this->assertEquals($pomodoroSession->id, $data['recent_subjects'][0]['id']);
        $this->assertEquals('最新のポモドーロメモ', $data['recent_subjects'][0]['notes']);

        $this->assertEquals('study_session', $data['recent_subjects'][1]['type']);
        $this->assertEquals($studySession->id, $data['recent_subjects'][1]['id']);
        $this->assertEquals('古い学習セッションメモ', $data['recent_subjects'][1]['notes']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     * 5件制限の確認
     */
    #[Test]
    public function 最近の学習履歴が5件に制限されること()
    {
        // 学習セッションを4つ作成
        for ($i = 0; $i < 4; $i++) {
            StudySession::factory()->create([
                'user_id' => $this->user->id,
                'subject_area_id' => $this->subjectArea->id,
                'started_at' => now()->subMinutes($i * 10),
                'ended_at' => now()->subMinutes($i * 10)->addMinutes(25),
                'duration_minutes' => 25,
            ]);
        }

        // ポモドーロセッションを4つ作成
        for ($i = 0; $i < 4; $i++) {
            PomodoroSession::factory()->create([
                'user_id' => $this->user->id,
                'subject_area_id' => $this->subjectArea->id,
                'session_type' => 'focus',
                'actual_duration' => 25,
                'is_completed' => true,
                'started_at' => now()->subMinutes(($i * 10) + 5), // 学習セッションとインターリーブ
            ]);
        }

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // recent_subjectsが5件に制限されることを確認
        $this->assertArrayHasKey('recent_subjects', $data);
        $this->assertCount(5, $data['recent_subjects']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     * 未完了のポモドーロセッションは表示されないこと
     */
    #[Test]
    public function 未完了のポモドーロセッションは表示されないこと()
    {
        // 完了したポモドーロセッション
        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'session_type' => 'focus',
            'actual_duration' => 25,
            'is_completed' => true,
            'started_at' => now(),
        ]);

        // 未完了のポモドーロセッション
        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'session_type' => 'focus',
            'actual_duration' => 0,
            'is_completed' => false,
            'started_at' => now()->subMinutes(10),
        ]);

        // break セッション（focusではない）
        PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'session_type' => 'short_break',
            'actual_duration' => 5,
            'is_completed' => true,
            'started_at' => now()->subMinutes(20),
        ]);

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // 完了したfocusセッションのみが表示されることを確認
        $this->assertArrayHasKey('recent_subjects', $data);
        $this->assertCount(1, $data['recent_subjects']);
        $this->assertEquals('pomodoro_session', $data['recent_subjects'][0]['type']);
        $this->assertEquals(25, $data['recent_subjects'][0]['duration_minutes']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     * 他のユーザーのデータは表示されないこと
     */
    #[Test]
    public function 他のユーザーのデータは表示されないこと()
    {
        $otherUser = User::factory()->create();
        $otherExamType = ExamType::factory()->create(['user_id' => $otherUser->id]);
        $otherSubjectArea = SubjectArea::factory()->create(['exam_type_id' => $otherExamType->id]);

        // 他のユーザーの学習セッション
        StudySession::factory()->create([
            'user_id' => $otherUser->id,
            'subject_area_id' => $otherSubjectArea->id,
            'started_at' => now(),
            'ended_at' => now()->addMinutes(60),
            'duration_minutes' => 60,
        ]);

        // 他のユーザーのポモドーロセッション
        PomodoroSession::factory()->create([
            'user_id' => $otherUser->id,
            'subject_area_id' => $otherSubjectArea->id,
            'session_type' => 'focus',
            'actual_duration' => 25,
            'is_completed' => true,
            'started_at' => now(),
        ]);

        // 自分の学習セッション
        StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => now()->subMinutes(10),
            'ended_at' => now()->subMinutes(10)->addMinutes(30),
            'duration_minutes' => 30,
        ]);

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // 自分のデータのみが表示されることを確認
        $this->assertArrayHasKey('recent_subjects', $data);
        $this->assertCount(1, $data['recent_subjects']);
        $this->assertEquals('study_session', $data['recent_subjects'][0]['type']);
        $this->assertEquals(30, $data['recent_subjects'][0]['duration_minutes']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     * subject_area_idがnullのポモドーロセッションの表示
     */
    #[Test]
    public function subject_area_idがnullのポモドーロセッションも正しく表示されること()
    {
        // subject_area_idがnullのポモドーロセッション
        $pomodoroSession = PomodoroSession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => null,
            'session_type' => 'focus',
            'actual_duration' => 25,
            'is_completed' => true,
            'started_at' => now(),
            'notes' => '分野なしポモドーロメモ',
        ]);

        // 認証ユーザーを設定
        $this->actingAs($this->user);

        // ダッシュボードAPIを呼び出し
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        // recent_subjectsが存在し、1件のポモドーロセッションが含まれることを確認
        $this->assertArrayHasKey('recent_subjects', $data);
        $this->assertCount(1, $data['recent_subjects']);

        $session = $data['recent_subjects'][0];
        $this->assertEquals('pomodoro_session', $session['type']);
        $this->assertEquals($pomodoroSession->id, $session['id']);
        $this->assertEquals('ポモドーロ学習', $session['subject_area_name']); // デフォルト名
        $this->assertNull($session['exam_type_name']);
        $this->assertEquals(25, $session['duration_minutes']);
        $this->assertEquals('分野なしポモドーロメモ', $session['notes']);
    }
}
