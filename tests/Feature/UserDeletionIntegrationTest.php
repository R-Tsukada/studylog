<?php

namespace Tests\Feature;

use App\Models\DailyStudySummary;
use App\Models\ExamType;
use App\Models\PomodoroSession;
use App\Models\StudyGoal;
use App\Models\StudySession;
use App\Models\SubjectArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserDeletionIntegrationTest extends TestCase
{
    use RefreshDatabase;
    

    /** @test */
    public function complete_user_deletion_workflow()
    {
        // 完全なユーザーデータセットを作成
        $user = User::factory()->create([
            'nickname' => 'テスト削除ユーザー',
            'email' => 'delete-integration@example.com',
            'password' => Hash::make('password123'),
        ]);

        // 複数の試験タイプを作成
        $examTypes = ExamType::factory(3)->create(['user_id' => $user->id]);

        $allSubjectAreas = collect();
        $allStudySessions = collect();
        $allStudyGoals = collect();
        $allPomodoroSessions = collect();

        foreach ($examTypes as $examType) {
            // 学習分野を作成
            $subjectAreas = SubjectArea::factory(5)->create([
                'exam_type_id' => $examType->id,
                'user_id' => $user->id,
            ]);
            $allSubjectAreas = $allSubjectAreas->concat($subjectAreas);

            // 学習セッションを作成
            foreach ($subjectAreas as $subjectArea) {
                $studySessions = StudySession::factory(15)->create([
                    'user_id' => $user->id,
                    'subject_area_id' => $subjectArea->id,
                ]);
                $allStudySessions = $allStudySessions->concat($studySessions);

                // ポモドーロセッションも作成
                $pomodoroSessions = PomodoroSession::factory(10)->create([
                    'user_id' => $user->id,
                    'subject_area_id' => $subjectArea->id,
                ]);
                $allPomodoroSessions = $allPomodoroSessions->concat($pomodoroSessions);
            }

            // 学習目標を作成
            $studyGoals = StudyGoal::factory(2)->create([
                'user_id' => $user->id,
                'exam_type_id' => $examType->id,
            ]);
            $allStudyGoals = $allStudyGoals->concat($studyGoals);
        }

        // 日次学習サマリーを作成（日付を分散させる）
        $dailySummaries = collect();
        for ($i = 0; $i < 30; $i++) {
            $dailySummaries->push(DailyStudySummary::factory()->create([
                'user_id' => $user->id,
                'study_date' => now()->subDays($i)->toDateString(),
            ]));
        }

        // 削除前にデータの存在を確認
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertEquals(3, ExamType::where('user_id', $user->id)->count());
        $this->assertEquals(15, SubjectArea::where('user_id', $user->id)->count());
        $this->assertEquals(225, StudySession::where('user_id', $user->id)->count());
        $this->assertEquals(6, StudyGoal::where('user_id', $user->id)->count());
        $this->assertEquals(150, PomodoroSession::where('user_id', $user->id)->count());
        $this->assertEquals(30, DailyStudySummary::where('user_id', $user->id)->count());

        Sanctum::actingAs($user);

        // アカウント削除を実行
        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'アカウントを削除しました',
            ]);

        // すべてのユーザーデータが削除されていることを確認
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertEquals(0, ExamType::where('user_id', $user->id)->count());
        $this->assertEquals(0, SubjectArea::where('user_id', $user->id)->count());
        $this->assertEquals(0, StudySession::where('user_id', $user->id)->count());
        $this->assertEquals(0, StudyGoal::where('user_id', $user->id)->count());
        $this->assertEquals(0, PomodoroSession::where('user_id', $user->id)->count());
        $this->assertEquals(0, DailyStudySummary::where('user_id', $user->id)->count());
    }

    /** @test */
    public function user_deletion_does_not_affect_other_users_data()
    {
        // 削除対象ユーザー
        $userToDelete = User::factory()->create([
            'email' => 'delete-me@example.com',
            'password' => Hash::make('password123'),
        ]);

        // 保持すべき他のユーザー
        $otherUser = User::factory()->create([
            'email' => 'keep-me@example.com',
        ]);

        // 両ユーザーのデータを作成
        $deleteUserExam = ExamType::factory()->create(['user_id' => $userToDelete->id]);
        $otherUserExam = ExamType::factory()->create(['user_id' => $otherUser->id]);

        $deleteUserSubject = SubjectArea::factory()->create([
            'exam_type_id' => $deleteUserExam->id,
            'user_id' => $userToDelete->id,
        ]);
        $otherUserSubject = SubjectArea::factory()->create([
            'exam_type_id' => $otherUserExam->id,
            'user_id' => $otherUser->id,
        ]);

        StudySession::factory(5)->create([
            'user_id' => $userToDelete->id,
            'subject_area_id' => $deleteUserSubject->id,
        ]);
        StudySession::factory(5)->create([
            'user_id' => $otherUser->id,
            'subject_area_id' => $otherUserSubject->id,
        ]);

        Sanctum::actingAs($userToDelete);

        // 削除前にデータの存在を確認
        $this->assertDatabaseHas('users', ['id' => $userToDelete->id]);
        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);

        // アカウント削除を実行
        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200);

        // 削除対象ユーザーのデータは削除される
        $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);
        $this->assertDatabaseMissing('exam_types', ['id' => $deleteUserExam->id]);
        $this->assertDatabaseMissing('subject_areas', ['id' => $deleteUserSubject->id]);

        // 他のユーザーのデータは保持される
        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
        $this->assertDatabaseHas('exam_types', ['id' => $otherUserExam->id]);
        $this->assertDatabaseHas('subject_areas', ['id' => $otherUserSubject->id]);
        $this->assertEquals(5, StudySession::where('user_id', $otherUser->id)->count());
    }

    /** @test */
    public function system_data_is_preserved_during_user_deletion()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // システムデータを作成
        $systemExamType = ExamType::factory()->create([
            'user_id' => null,
            'is_system' => true,
            'name' => 'システム基本情報技術者',
        ]);

        $systemSubjectArea = SubjectArea::factory()->create([
            'exam_type_id' => $systemExamType->id,
            'user_id' => null,
            'is_system' => true,
            'name' => 'システム基礎理論',
        ]);

        // ユーザーデータを作成
        $userExamType = ExamType::factory()->create([
            'user_id' => $user->id,
            'is_system' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200);

        // ユーザーデータは削除される
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('exam_types', ['id' => $userExamType->id]);

        // システムデータは保持される
        $this->assertDatabaseHas('exam_types', [
            'id' => $systemExamType->id,
            'is_system' => true,
        ]);
        $this->assertDatabaseHas('subject_areas', [
            'id' => $systemSubjectArea->id,
            'is_system' => true,
        ]);
    }

    /** @test */
    public function google_user_deletion_workflow()
    {
        $googleUser = User::factory()->create([
            'nickname' => 'Google削除ユーザー',
            'email' => 'google-delete@example.com',
            'password' => null,
            'google_id' => '123456789',
        ]);

        // Google ユーザーのデータを作成
        $examType = ExamType::factory()->create(['user_id' => $googleUser->id]);
        StudySession::factory(10)->create(['user_id' => $googleUser->id]);

        Sanctum::actingAs($googleUser);

        // Google ユーザーはパスワード不要で削除可能
        $response = $this->deleteJson('/api/auth/account', [
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'アカウントを削除しました',
            ]);

        // ユーザーとデータが削除されていることを確認
        $this->assertDatabaseMissing('users', ['id' => $googleUser->id]);
        $this->assertDatabaseMissing('exam_types', ['id' => $examType->id]);
        $this->assertEquals(0, StudySession::where('user_id', $googleUser->id)->count());
    }

    /** @test */
    public function user_deletion_with_mixed_data_types()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // 様々なタイプのデータを作成
        $userExamType = ExamType::factory()->create([
            'user_id' => $user->id,
            'is_system' => false,
        ]);

        $systemExamType = ExamType::factory()->create([
            'user_id' => null,
            'is_system' => true,
        ]);

        // ユーザー作成の分野とシステム分野
        $userSubjectArea = SubjectArea::factory()->create([
            'exam_type_id' => $userExamType->id,
            'user_id' => $user->id,
            'is_system' => false,
        ]);

        $systemSubjectArea = SubjectArea::factory()->create([
            'exam_type_id' => $systemExamType->id,
            'user_id' => null,
            'is_system' => true,
        ]);

        // ユーザーのセッション（ユーザー分野とシステム分野両方で）
        StudySession::factory(5)->create([
            'user_id' => $user->id,
            'subject_area_id' => $userSubjectArea->id,
        ]);

        StudySession::factory(5)->create([
            'user_id' => $user->id,
            'subject_area_id' => $systemSubjectArea->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200);

        // ユーザーとユーザー作成データは削除される
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('exam_types', ['id' => $userExamType->id]);
        $this->assertDatabaseMissing('subject_areas', ['id' => $userSubjectArea->id]);
        $this->assertEquals(0, StudySession::where('user_id', $user->id)->count());

        // システムデータは保持される
        $this->assertDatabaseHas('exam_types', [
            'id' => $systemExamType->id,
            'is_system' => true,
        ]);
        $this->assertDatabaseHas('subject_areas', [
            'id' => $systemSubjectArea->id,
            'is_system' => true,
        ]);
    }

    /** @test */
    public function user_deletion_performance_with_large_dataset()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // 大量のデータを作成（パフォーマンステスト）
        $examTypes = ExamType::factory(10)->create(['user_id' => $user->id]);

        foreach ($examTypes->take(3) as $examType) { // 最初の3つだけで大量データ作成
            $subjectAreas = SubjectArea::factory(20)->create([
                'exam_type_id' => $examType->id,
                'user_id' => $user->id,
            ]);

            foreach ($subjectAreas->take(5) as $subjectArea) { // 最初の5つだけ
                StudySession::factory(50)->create([
                    'user_id' => $user->id,
                    'subject_area_id' => $subjectArea->id,
                ]);

                PomodoroSession::factory(30)->create([
                    'user_id' => $user->id,
                    'subject_area_id' => $subjectArea->id,
                ]);
            }
        }

        // 削除前の時間を記録
        $startTime = microtime(true);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);

        // パフォーマンス確認（5秒以内で完了することを確認）
        $this->assertLessThan(5.0, $executionTime, '大量データの削除が5秒以内に完了すること');

        // すべてのデータが削除されていることを確認
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertEquals(0, ExamType::where('user_id', $user->id)->count());
        $this->assertEquals(0, SubjectArea::where('user_id', $user->id)->count());
        $this->assertEquals(0, StudySession::where('user_id', $user->id)->count());
        $this->assertEquals(0, PomodoroSession::where('user_id', $user->id)->count());
    }
}
