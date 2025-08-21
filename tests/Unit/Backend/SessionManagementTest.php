<?php

namespace Tests\Unit\Backend;

use App\Models\ExamType;
use App\Models\StudySession;
use App\Models\SubjectArea;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $subjectArea;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用データ準備
        $this->user = User::factory()->create();
        $examType = ExamType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Exam',
            'is_system' => false,
        ]);
        $this->subjectArea = SubjectArea::factory()->create([
            'user_id' => $this->user->id,
            'exam_type_id' => $examType->id,
            'name' => 'Test Subject',
            'is_system' => false,
        ]);
    }

    /**
     * @test
     * TDD Red Phase: 安全なセッション開始機能のテスト
     */
    public function test_safe_session_start_with_no_existing_session()
    {
        // 既存のアクティブセッションがない状態で新しいセッションを開始
        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/study-sessions/start-safe', [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => 'Test comment',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '学習セッションを開始しました',
            ]);

        // データベースに新しいセッションが作成されているか確認
        $this->assertDatabaseHas('study_sessions', [
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => 'Test comment',
            'ended_at' => null,
        ]);
    }

    /**
     * @test
     * TDD Red Phase: 既存セッション自動終了機能のテスト
     */
    public function test_safe_session_start_with_existing_active_session_auto_closes_old_session()
    {
        // 既存のアクティブセッションを作成
        $oldSession = StudySession::create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHours(2),
            'study_comment' => 'Old session',
        ]);

        $this->assertNull($oldSession->ended_at, '古いセッションがアクティブであることを確認');

        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/study-sessions/start-safe', [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => 'New session',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '学習セッションを開始しました（前のセッションを自動終了）',
                'auto_closed_session' => [
                    'id' => $oldSession->id,
                    'reason' => 'システム自動終了（新セッション開始のため）',
                ],
            ]);

        // 古いセッションが自動終了されているか確認
        $oldSession->refresh();
        $this->assertNotNull($oldSession->ended_at, '古いセッションが自動終了されている');
        $this->assertStringContainsString('システム自動終了（新セッション開始のため）', $oldSession->study_comment);

        // 新しいセッションが作成されているか確認
        $this->assertDatabaseHas('study_sessions', [
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => 'New session',
            'ended_at' => null,
        ]);
    }

    /**
     * @test
     * TDD Red Phase: 複数の古いセッション処理のテスト
     */
    public function test_safe_session_start_handles_multiple_old_sessions()
    {
        // 複数の古いアクティブセッションを作成（エラー状態）
        $oldSession1 = StudySession::create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHours(3),
            'study_comment' => 'Old session 1',
        ]);

        $oldSession2 = StudySession::create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHours(1),
            'study_comment' => 'Old session 2',
        ]);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/study-sessions/start-safe', [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => 'New session',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'cleaned_sessions_count' => 2,
            ]);

        // 全ての古いセッションが自動終了されているか確認
        $oldSession1->refresh();
        $oldSession2->refresh();
        $this->assertNotNull($oldSession1->ended_at);
        $this->assertNotNull($oldSession2->ended_at);
    }

    /**
     * @test
     * TDD Red Phase: 冪等性テスト（同じリクエストの重複実行）
     */
    public function test_safe_session_start_idempotency()
    {
        $this->actingAs($this->user, 'sanctum');

        // 1回目のリクエスト
        $response1 = $this->postJson('/api/study-sessions/start-safe', [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => 'Test session',
        ]);

        $response1->assertStatus(201);
        $sessionId1 = $response1->json('session.id');

        // 短時間での2回目のリクエスト（重複リクエスト想定）
        $response2 = $this->postJson('/api/study-sessions/start-safe', [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => 'Test session',
        ]);

        // 2回目は既存セッション自動終了処理が働く
        $response2->assertStatus(201);
        $sessionId2 = $response2->json('session.id');

        // 新しいセッションが作成されている
        $this->assertNotEquals($sessionId1, $sessionId2);

        // アクティブなセッションは1つだけ
        $activeSessions = StudySession::active()->where('user_id', $this->user->id)->get();
        $this->assertCount(1, $activeSessions);
    }

    /**
     * @test
     * TDD Red Phase: 状態同期確認エンドポイント
     */
    public function test_session_sync_endpoint_detects_inconsistencies()
    {
        // データベースに古いアクティブセッションを作成（25時間前でcleanup推奨状態）
        $oldSession = StudySession::create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHours(25),
            'study_comment' => 'Old session',
        ]);

        $this->actingAs($this->user, 'sanctum');

        // 同期確認エンドポイント（まだ実装されていない）
        $response = $this->getJson('/api/study-sessions/sync-status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'has_active_session' => true,
                'session_id' => $oldSession->id,
                'needs_cleanup' => true,
                'recommendation' => 'force_end_and_start_new',
            ]);
    }

    /**
     * @test
     * TDD Red Phase: 強制クリーンアップエンドポイント
     */
    public function test_force_cleanup_all_active_sessions()
    {
        // 複数の古いアクティブセッションを作成
        StudySession::create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHours(2),
            'study_comment' => 'Old session 1',
        ]);

        StudySession::create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subHours(1),
            'study_comment' => 'Old session 2',
        ]);

        $this->actingAs($this->user, 'sanctum');

        // 強制クリーンアップエンドポイント（まだ実装されていない）
        $response = $this->postJson('/api/study-sessions/force-cleanup', [
            'reason' => 'Manual cleanup from user interface',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'cleaned_sessions_count' => 2,
                'message' => '古いセッションを2件クリーンアップしました',
            ]);

        // 全てのセッションが終了されているか確認
        $activeSessions = StudySession::active()->where('user_id', $this->user->id)->count();
        $this->assertEquals(0, $activeSessions);
    }

    /**
     * @test
     * TDD Red Phase: バリデーションエラーハンドリング
     */
    public function test_safe_session_start_validation_errors()
    {
        $this->actingAs($this->user, 'sanctum');

        // 必須フィールドなし
        $response = $this->postJson('/api/study-sessions/start-safe', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject_area_id', 'study_comment']);

        // 存在しないsubject_area_id
        $response = $this->postJson('/api/study-sessions/start-safe', [
            'subject_area_id' => 99999,
            'study_comment' => 'Test comment',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject_area_id']);
    }

    /**
     * @test
     * TDD Red Phase: 認証エラーハンドリング
     */
    public function test_safe_session_start_authentication_required()
    {
        // 認証なしでアクセス
        $response = $this->postJson('/api/study-sessions/start-safe', [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => 'Test comment',
        ]);

        $response->assertStatus(401);
    }
}
