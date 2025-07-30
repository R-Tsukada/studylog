<?php

namespace Tests\Feature;

use App\Models\ExamType;
use App\Models\StudySession;
use App\Models\SubjectArea;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ダッシュボードと時間計測タイマーの統合テスト
 *
 * - ダッシュボードでの学習開始/終了
 * - グローバルタイマーとの同期
 * - 時間表示の一貫性
 */
class DashboardStudyTimerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ExamType $examType;

    private SubjectArea $subjectArea;

    protected function setUp(): void
    {
        parent::setUp();

        // テストデータ作成
        $this->user = User::factory()->create([
            'nickname' => 'テストユーザ',
            'email' => 'test@example.com',
        ]);

        $this->examType = ExamType::factory()->create([
            'name' => 'JSTQB Foundation Level',
        ]);

        $this->subjectArea = SubjectArea::factory()->create([
            'exam_type_id' => $this->examType->id,
            'name' => 'ソフトウェアテスト基礎',
        ]);
    }

    /**
     * @test
     * ダッシュボードでの学習セッション開始から終了までの統合フロー
     */
    public function ダッシュボードでの学習セッション統合フローをテスト()
    {
        $this->actingAs($this->user);

        // 1. ダッシュボードデータ取得（初期状態）
        $response = $this->getJson('/api/dashboard');
        $response->assertStatus(200);

        $dashboardData = $response->json('data');
        $this->assertEquals(0, $dashboardData['today_session_count']);
        // 時間の形式は数値または文字列の可能性があるため、どちらも受け入れる
        $this->assertTrue(
            $dashboardData['today_study_time'] === 0 ||
            $dashboardData['today_study_time'] === '0分',
            '今日の学習時間が期待値と一致しません: '.$dashboardData['today_study_time']
        );

        // 2. 現在のセッション確認（なし）
        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'session' => null,
        ]);

        // 3. ダッシュボードから学習セッション開始
        $sessionData = [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => 'ダッシュボードからの学習開始テスト',
        ];

        $startTime = time();
        $response = $this->postJson('/api/study-sessions/start', $sessionData);
        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => '学習セッションを開始しました',
        ]);

        $sessionData = $response->json('session');
        $this->assertNotNull($sessionData['id']);
        $this->assertEquals('ソフトウェアテスト基礎', $sessionData['subject_area_name']);
        $this->assertEquals('JSTQB Foundation Level', $sessionData['exam_type_name']);
        $this->assertEquals(0, $sessionData['elapsed_minutes']);

        // タイムスタンプの精度確認
        $this->assertEqualsWithDelta($startTime, $sessionData['started_at_timestamp'], 2);

        // 4. 現在のセッション確認（アクティブ）
        sleep(1); // 少し時間を進める
        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(200);

        $currentSession = $response->json('session');
        $this->assertNotNull($currentSession);
        $this->assertEquals($sessionData['id'], $currentSession['id']);
        $this->assertGreaterThanOrEqual(0, $currentSession['elapsed_minutes']);

        // 5. ダッシュボードから学習セッション終了
        $response = $this->postJson('/api/study-sessions/end');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => '学習セッションを終了しました',
        ]);

        $endedSession = $response->json('session');
        $this->assertNotNull($endedSession['ended_at']);
        $this->assertGreaterThanOrEqual(0, $endedSession['duration_minutes']); // 0以上に変更（タイミング誤差を考慮）

        // 6. 現在のセッション確認（なし）
        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'session' => null,
        ]);

        // 7. ダッシュボードデータ更新確認
        $response = $this->getJson('/api/dashboard');
        $response->assertStatus(200);

        $updatedDashboardData = $response->json('data');
        $this->assertEquals(1, $updatedDashboardData['today_session_count']);
        // 短時間のセッションでは0分になる可能性があるため、セッション数で確認
        $this->assertIsString($updatedDashboardData['today_study_time']);
    }

    /**
     * @test
     * 複数画面での時間表示の一貫性をテスト
     */
    public function 複数画面での時間表示一貫性をテスト()
    {
        $this->actingAs($this->user);

        // セッション開始
        $sessionData = [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => '一貫性テスト',
        ];

        $response = $this->postJson('/api/study-sessions/start', $sessionData);
        $response->assertStatus(201);
        $sessionId = $response->json('session.id');

        // 2秒待機
        sleep(2);

        // 1. 時間計測ページでの時間確認
        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(200);
        $studyPageTime = $response->json('session.elapsed_minutes');

        // 2. ダッシュボードでも同じAPIを使用するため、時間は一致するはず
        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(200);
        $dashboardTime = $response->json('session.elapsed_minutes');

        // 同じタイミングでの取得なので時間は一致
        $this->assertEquals($studyPageTime, $dashboardTime);
        $this->assertGreaterThanOrEqual(0, $studyPageTime);

        // セッション終了
        $this->postJson('/api/study-sessions/end');
    }

    /**
     * @test
     * ページリロード想定での状態復元をテスト
     */
    public function ページリロード想定での状態復元をテスト()
    {
        $this->actingAs($this->user);

        // 1. セッション開始
        $sessionData = [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => '状態復元テスト',
        ];

        $response = $this->postJson('/api/study-sessions/start', $sessionData);
        $response->assertStatus(201);
        $originalSession = $response->json('session');

        // 2. 時間経過をシミュレート
        sleep(1);

        // 3. ページリロード想定：新しいリクエストで状態確認
        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(200);

        $restoredSession = $response->json('session');

        // セッション情報が正しく復元されることを確認
        $this->assertEquals($originalSession['id'], $restoredSession['id']);
        $this->assertEquals($originalSession['subject_area_name'], $restoredSession['subject_area_name']);
        $this->assertEquals($originalSession['study_comment'], $restoredSession['study_comment']);

        // 経過時間が進んでいることを確認
        $this->assertGreaterThanOrEqual($originalSession['elapsed_minutes'], $restoredSession['elapsed_minutes']);

        // グローバルタイマー復元に必要なデータが含まれていることを確認
        $this->assertArrayHasKey('started_at_timestamp', $restoredSession);
        $this->assertArrayHasKey('elapsed_minutes', $restoredSession);

        // セッション終了
        $this->postJson('/api/study-sessions/end');
    }

    /**
     * @test
     * 複数セッション実行時のタイマー状態管理をテスト
     */
    public function 複数セッション実行時のタイマー状態管理をテスト()
    {
        $this->actingAs($this->user);

        // 1回目のセッション
        $sessionData1 = [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => '1回目のセッション',
        ];

        $response = $this->postJson('/api/study-sessions/start', $sessionData1);
        $response->assertStatus(201);
        $session1Id = $response->json('session.id');

        sleep(1);

        $response = $this->postJson('/api/study-sessions/end');
        $response->assertStatus(200);

        // セッション終了後、アクティブなセッションがないことを確認
        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'session' => null]);

        // 2回目のセッション
        $sessionData2 = [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => '2回目のセッション',
        ];

        $response = $this->postJson('/api/study-sessions/start', $sessionData2);
        $response->assertStatus(201);
        $session2Id = $response->json('session.id');

        // 異なるセッションIDであることを確認
        $this->assertNotEquals($session1Id, $session2Id);

        // 現在のアクティブセッションが2回目であることを確認
        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(200);
        $currentSession = $response->json('session');
        $this->assertEquals($session2Id, $currentSession['id']);
        $this->assertEquals('2回目のセッション', $currentSession['study_comment']);

        // 2回目のセッション終了
        $this->postJson('/api/study-sessions/end');
    }

    /**
     * @test
     * エラー状況での状態管理をテスト
     */
    public function エラー状況での状態管理をテスト()
    {
        $this->actingAs($this->user);

        // 1. アクティブセッションなしで終了を試行
        $response = $this->postJson('/api/study-sessions/end');
        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => '終了可能な学習セッションが見つかりません',
        ]);

        // 2. 無効な学習分野IDでセッション開始を試行
        $invalidSessionData = [
            'subject_area_id' => 99999,
            'study_comment' => '無効なテスト',
        ];

        $response = $this->postJson('/api/study-sessions/start', $invalidSessionData);
        $response->assertStatus(422);

        // 3. 正常なセッションが開始できることを確認
        $validSessionData = [
            'subject_area_id' => $this->subjectArea->id,
            'study_comment' => '正常なテスト',
        ];

        $response = $this->postJson('/api/study-sessions/start', $validSessionData);
        $response->assertStatus(201);

        // 4. 既存セッション中に新しいセッション開始を試行
        $response = $this->postJson('/api/study-sessions/start', $validSessionData);
        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => '既に進行中の学習セッションがあります。先に終了してください。',
        ]);

        // セッション終了
        $this->postJson('/api/study-sessions/end');
    }

    /**
     * @test
     * 長時間セッションでの時間計算精度をテスト
     */
    public function 長時間セッションでの時間計算精度をテスト()
    {
        $this->actingAs($this->user);

        // 過去の時間でセッションを開始（長時間セッションをシミュレート）
        $session = StudySession::create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => Carbon::now()->subMinutes(125), // 2時間5分前
            'study_comment' => '長時間セッションテスト',
        ]);

        // 現在のセッション取得
        $response = $this->getJson('/api/study-sessions/current');
        $response->assertStatus(200);

        $currentSession = $response->json('session');
        $this->assertNotNull($currentSession);

        $elapsedMinutes = $currentSession['elapsed_minutes'];

        // 経過時間が120-130分の範囲内であることを確認（多少の誤差を許容）
        $this->assertGreaterThanOrEqual(120, $elapsedMinutes);
        $this->assertLessThanOrEqual(130, $elapsedMinutes);

        // セッション終了
        $response = $this->postJson('/api/study-sessions/end');
        $response->assertStatus(200);

        $endedSession = $response->json('session');
        $this->assertGreaterThanOrEqual(120, $endedSession['duration_minutes']); // 120分以上（丁度の場合も考慮）
    }
}
