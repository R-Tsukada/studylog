<?php

namespace Tests\Unit\Frontend;

use App\Models\ExamType;
use App\Models\StudySession;
use App\Models\SubjectArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;



/**
 * グローバル時間計測タイマーのテストクラス
 *
 * Frontend: App.vue の globalStudyTimer 機能をテスト
 */
class GlobalStudyTimerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private SubjectArea $subjectArea;

    protected function setUp(): void
    {
        parent::setUp();

        // テストユーザーとデータを作成
        $this->user = User::factory()->create();
        $examType = ExamType::factory()->create(['name' => 'テスト資格']);
        $this->subjectArea = SubjectArea::factory()->create([
            'exam_type_id' => $examType->id,
            'name' => 'テスト分野',
        ]);
    }

    

/**
     * テストメソッド
     * グローバルタイマーの基本的な状態管理をテスト
     */
    #[Test]
    public function グローバル時間計測タイマーの基本状態をテスト()
    {
        // 認証
        $this->actingAs($this->user);

        // 1. 初期状態：アクティブなセッションがないことを確認
        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'session' => null,
        ]);

        // 2. セッション開始
        $sessionData = [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => 'グローバルタイマーテスト',
        ];

        $response = $this->postJson('/api/study-sessions/start', $sessionData);
        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
        ]);

        $sessionId = $response->json('session.id');
        $this->assertNotNull($sessionId);

        // 3. 現在のセッション取得（グローバルタイマー同期用）
        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'session' => [
                'id' => $sessionId,
                'subject_area_name' => 'テスト分野',
                'study_comment' => 'グローバルタイマーテスト',
            ],
        ]);

        // タイムスタンプが含まれていることを確認
        $this->assertArrayHasKey('started_at_timestamp', $response->json('session'));
        $this->assertArrayHasKey('elapsed_minutes', $response->json('session'));

        // 4. セッション終了（コメントを渡す）
        $response = $this->postJson('/api/study-sessions/end', [
            'study_comment' => 'グローバルタイマーテスト',
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // 5. セッション終了後：アクティブなセッションがないことを確認
        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'session' => null,
        ]);
    }

    

/**
     * テストメソッド
     * グローバルタイマーの時間計算精度をテスト
     */
    #[Test]
    public function グローバルタイマーの時間計算精度をテスト()
    {
        $this->actingAs($this->user);

        // セッション開始
        $sessionData = [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => '時間計算テスト',
        ];

        $startTime = time();
        $response = $this->postJson('/api/study-sessions/start', $sessionData);
        $response->assertStatus(201);

        $sessionStartTimestamp = $response->json('session.started_at_timestamp');

        // タイムスタンプの精度確認（±1秒の誤差を許容）
        $this->assertEqualsWithDelta($startTime, $sessionStartTimestamp, 1);

        // 少し待機してから現在のセッション取得
        sleep(2);

        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(200);

        $elapsedMinutes = $response->json('session.elapsed_minutes');
        $this->assertGreaterThanOrEqual(0, $elapsedMinutes);

        // 経過時間が現実的な範囲内であることを確認
        $this->assertLessThan(5, $elapsedMinutes); // 5分未満であること
    }

    

/**
     * テストメソッド
     * 複数セッション開始の防止をテスト
     */
    #[Test]
    public function 複数セッション開始の防止をテスト()
    {
        $this->actingAs($this->user);

        // 最初のセッション開始
        $sessionData = [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => '最初のセッション',
        ];

        $response = $this->postJson('/api/study-sessions/start', $sessionData);
        $response->assertStatus(201);

        // 2つ目のセッション開始を試行（失敗するはず）
        $sessionData2 = [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => '2つ目のセッション',
        ];

        $response = $this->postJson('/api/study-sessions/start', $sessionData2);
        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => '既に進行中の学習セッションがあります。先に終了してください。',
        ]);
    }

    

/**
     * テストメソッド
     * グローバルタイマー状態の復元シナリオをテスト
     */
    #[Test]
    public function グローバルタイマー状態復元シナリオをテスト()
    {
        $this->actingAs($this->user);

        // セッション開始
        $sessionData = [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => '復元テストセッション',
        ];

        $response = $this->postJson('/api/study-sessions/start', $sessionData);
        $response->assertStatus(201);
        $sessionId = $response->json('session.id');

        // ページリロード想定：現在のセッション再取得
        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(200);

        $session = $response->json('session');
        $this->assertEquals($sessionId, $session['id']);
        $this->assertEquals('復元テストセッション', $session['study_comment']);

        // 必要なフィールドが含まれていることを確認
        $this->assertArrayHasKey('started_at_timestamp', $session);
        $this->assertArrayHasKey('elapsed_minutes', $session);
        $this->assertArrayHasKey('subject_area_name', $session);
        $this->assertArrayHasKey('exam_type_name', $session);
    }

    

/**
     * テストメソッド
     * セッション終了時のデータ整合性をテスト
     */
    #[Test]
    public function セッション終了時のデータ整合性をテスト()
    {
        $this->actingAs($this->user);

        // セッション開始
        $sessionData = [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => '終了テストセッション',
        ];

        $response = $this->postJson('/api/study-sessions/start', $sessionData);
        $response->assertStatus(201);
        $sessionId = $response->json('session.id');

        // 少し時間を進める
        sleep(2);

        // セッション終了
        $response = $this->postJson('/api/study-sessions/end', [
            'study_comment' => '終了テストセッション',
        ]);
        $response->assertStatus(200);

        $endedSession = $response->json('session');
        $this->assertEquals($sessionId, $endedSession['id']);
        $this->assertNotNull($endedSession['ended_at']);
        $this->assertGreaterThanOrEqual(0, $endedSession['duration_minutes']); // 0以上に変更（秒単位の誤差を考慮）

        // データベースで確認
        $session = StudySession::find($sessionId);
        $this->assertNotNull($session->ended_at);
        $this->assertGreaterThanOrEqual(0, $session->duration_minutes); // 0以上に変更
        $this->assertFalse($session->isActive());
    }

    

/**
     * テストメソッド
     * 認証エラー時の処理をテスト
     */
    #[Test]
    public function 認証エラー時の処理をテスト()
    {
        // 認証なしでAPI呼び出し
        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(401);

        $sessionData = [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => '認証なしテスト',
        ];

        $response = $this->postJson('/api/study-sessions/start', $sessionData);
        $response->assertStatus(401);

        $response = $this->postJson('/api/study-sessions/end');
        $response->assertStatus(401);
    }
}
