<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\ExamType;
use App\Models\SubjectArea;
use App\Models\StudySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudyManagementIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;


    /** @test */
    public function complete_study_management_workflow()
    {
        // 1. ユーザー登録
        $registrationResponse = $this->postJson('/api/auth/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $registrationResponse->assertStatus(201);
        $token = $registrationResponse->json('token');
        $user = User::where('email', 'test@example.com')->first();

        // 2. 認証ヘッダーを設定
        $headers = ['Authorization' => "Bearer $token"];

        // 3. 試験タイプを作成
        $examTypeResponse = $this->postJson('/api/user/exam-types', [
            'name' => '基本情報技術者試験',
            'description' => 'IT業界の基礎知識を証明する国家試験',
            'exam_date' => '2025-06-15',
            'exam_notes' => '合格基準：60%以上',
            'color' => '#3B82F6',
        ], $headers);

        $examTypeResponse->assertStatus(201);
        $examType = $examTypeResponse->json('exam_type');

        // 4. 学習分野を複数作成
        $subjects = [
            'データベース設計',
            'アルゴリズム',
            'ネットワーク技術',
            'プログラミング'
        ];

        $createdSubjects = [];
        foreach ($subjects as $subjectName) {
            $subjectResponse = $this->postJson('/api/user/subject-areas', [
                'exam_type_id' => $examType['id'],
                'name' => $subjectName,
            ], $headers);

            $subjectResponse->assertStatus(201);
            $createdSubjects[] = $subjectResponse->json('subject_area');
        }

        // 5. 試験タイプ一覧を取得して確認
        $examTypesResponse = $this->getJson('/api/user/exam-types', $headers);
        $examTypesResponse->assertStatus(200);
        
        $examTypes = $examTypesResponse->json('exam_types');
        $this->assertCount(1, $examTypes);
        $this->assertEquals('基本情報技術者試験', $examTypes[0]['name']);

        // 6. 学習分野一覧を取得して確認
        $subjectAreasResponse = $this->getJson('/api/user/subject-areas', $headers);
        $subjectAreasResponse->assertStatus(200);
        
        $subjectAreas = $subjectAreasResponse->json('subject_areas');
        $this->assertCount(4, $subjectAreas);
        
        $subjectNames = collect($subjectAreas)->pluck('name')->toArray();
        foreach ($subjects as $subject) {
            $this->assertContains($subject, $subjectNames);
        }

        // 7. 試験タイプを更新
        $updateExamResponse = $this->putJson("/api/user/exam-types/{$examType['id']}", [
            'name' => '基本情報技術者試験（更新版）',
            'description' => '更新された説明',
            'exam_date' => '2025-07-20',
            'exam_notes' => '更新されたメモ',
            'color' => '#10B981',
        ], $headers);

        $updateExamResponse->assertStatus(200);

        // 8. 学習分野を更新
        $firstSubject = $createdSubjects[0];
        $updateSubjectResponse = $this->putJson("/api/user/subject-areas/{$firstSubject['id']}", [
            'exam_type_id' => $examType['id'],
            'name' => 'データベース設計（上級）',
        ], $headers);

        $updateSubjectResponse->assertStatus(200);

        // 9. 学習分野を削除（学習履歴がない場合）
        $lastSubject = $createdSubjects[3];
        $deleteSubjectResponse = $this->deleteJson("/api/user/subject-areas/{$lastSubject['id']}", [], $headers);
        $deleteSubjectResponse->assertStatus(200);

        // 10. 最終的な状態を確認
        $finalExamTypesResponse = $this->getJson('/api/user/exam-types', $headers);
        $finalExamTypes = $finalExamTypesResponse->json('exam_types');
        $this->assertEquals('基本情報技術者試験（更新版）', $finalExamTypes[0]['name']);

        $finalSubjectAreasResponse = $this->getJson('/api/user/subject-areas', $headers);
        $finalSubjectAreas = $finalSubjectAreasResponse->json('subject_areas');
        $this->assertCount(3, $finalSubjectAreas); // 1つ削除されたので3つ

        $finalSubjectNames = collect($finalSubjectAreas)->pluck('name')->toArray();
        $this->assertContains('データベース設計（上級）', $finalSubjectNames);
        $this->assertNotContains('プログラミング', $finalSubjectNames);
    }

    /** @test */
    public function user_data_isolation_works_correctly()
    {
        // 2人のユーザーを作成
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        // User1の試験タイプを作成
        Sanctum::actingAs($user1);
        $user1ExamResponse = $this->postJson('/api/user/exam-types', [
            'name' => 'User1の試験',
            'description' => 'User1専用',
        ]);
        $user1ExamResponse->assertStatus(201);
        $user1Exam = $user1ExamResponse->json('exam_type');

        // User1の学習分野を作成
        $user1SubjectResponse = $this->postJson('/api/user/subject-areas', [
            'exam_type_id' => $user1Exam['id'],
            'name' => 'User1の分野',
        ]);
        $user1SubjectResponse->assertStatus(201);

        // User2の試験タイプを作成
        Sanctum::actingAs($user2);
        $user2ExamResponse = $this->postJson('/api/user/exam-types', [
            'name' => 'User2の試験',
            'description' => 'User2専用',
        ]);
        $user2ExamResponse->assertStatus(201);
        $user2Exam = $user2ExamResponse->json('exam_type');

        // User2の学習分野を作成
        $user2SubjectResponse = $this->postJson('/api/user/subject-areas', [
            'exam_type_id' => $user2Exam['id'],
            'name' => 'User2の分野',
        ]);
        $user2SubjectResponse->assertStatus(201);

        // User1のデータ取得（User1でログイン）
        Sanctum::actingAs($user1);
        $user1ExamsResponse = $this->getJson('/api/user/exam-types');
        $user1Exams = $user1ExamsResponse->json('exam_types');
        
        $this->assertCount(1, $user1Exams);
        $this->assertEquals('User1の試験', $user1Exams[0]['name']);

        $user1SubjectsResponse = $this->getJson('/api/user/subject-areas');
        $user1Subjects = $user1SubjectsResponse->json('subject_areas');
        
        $this->assertCount(1, $user1Subjects);
        $this->assertEquals('User1の分野', $user1Subjects[0]['name']);

        // User2のデータ取得（User2でログイン）
        Sanctum::actingAs($user2);
        $user2ExamsResponse = $this->getJson('/api/user/exam-types');
        $user2Exams = $user2ExamsResponse->json('exam_types');
        
        $this->assertCount(1, $user2Exams);
        $this->assertEquals('User2の試験', $user2Exams[0]['name']);

        $user2SubjectsResponse = $this->getJson('/api/user/subject-areas');
        $user2Subjects = $user2SubjectsResponse->json('subject_areas');
        
        $this->assertCount(1, $user2Subjects);
        $this->assertEquals('User2の分野', $user2Subjects[0]['name']);

        // User1がUser2のデータにアクセスできないことを確認
        Sanctum::actingAs($user1);
        $unauthorizedUpdateResponse = $this->putJson("/api/user/exam-types/{$user2Exam['id']}", [
            'name' => '攻撃試行',
        ]);
        $unauthorizedUpdateResponse->assertStatus(404);

        $unauthorizedDeleteResponse = $this->deleteJson("/api/user/exam-types/{$user2Exam['id']}");
        $unauthorizedDeleteResponse->assertStatus(404);
    }

    /** @test */
    public function system_data_and_user_data_coexistence()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // システム標準の試験タイプを手動作成
        $systemExam = ExamType::create([
            'code' => 'jstqb_fl',
            'name' => 'JSTQB Foundation Level',
            'description' => 'システム標準試験',
            'user_id' => null,
            'is_system' => true,
            'is_active' => true,
        ]);

        // システム標準の学習分野を作成
        $systemSubject = SubjectArea::create([
            'exam_type_id' => $systemExam->id,
            'code' => 'test_planning',
            'name' => 'テスト計画',
            'description' => 'システム標準分野',
            'user_id' => null,
            'is_system' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // ユーザー固有の試験タイプを作成
        $userExamResponse = $this->postJson('/api/user/exam-types', [
            'name' => 'ユーザー固有試験',
            'description' => 'ユーザーが作成',
        ]);
        $userExamResponse->assertStatus(201);
        $userExam = $userExamResponse->json('exam_type');

        // ユーザー固有の学習分野を作成
        $userSubjectResponse = $this->postJson('/api/user/subject-areas', [
            'exam_type_id' => $userExam['id'],
            'name' => 'ユーザー固有分野',
        ]);
        $userSubjectResponse->assertStatus(201);

        // システム標準の試験タイプに学習分野を追加
        $userSubjectInSystemExamResponse = $this->postJson('/api/user/subject-areas', [
            'exam_type_id' => $systemExam->id,
            'name' => 'ユーザー追加分野',
        ]);
        $userSubjectInSystemExamResponse->assertStatus(201);

        // 取得時に両方表示されることを確認
        $examTypesResponse = $this->getJson('/api/user/exam-types');
        $examTypes = $examTypesResponse->json('exam_types');
        
        $this->assertCount(2, $examTypes); // システム標準 + ユーザー固有
        
        $examNames = collect($examTypes)->pluck('name')->toArray();
        $this->assertContains('JSTQB Foundation Level', $examNames);
        $this->assertContains('ユーザー固有試験', $examNames);

        $subjectAreasResponse = $this->getJson('/api/user/subject-areas');
        $subjectAreas = $subjectAreasResponse->json('subject_areas');
        
        $this->assertCount(3, $subjectAreas); // システム標準1 + ユーザー固有1 + ユーザーがシステム試験に追加1
        
        $subjectNames = collect($subjectAreas)->pluck('name')->toArray();
        $this->assertContains('テスト計画', $subjectNames);
        $this->assertContains('ユーザー固有分野', $subjectNames);
        $this->assertContains('ユーザー追加分野', $subjectNames);

        // システム標準データは編集・削除できないことを確認
        $updateSystemExamResponse = $this->putJson("/api/user/exam-types/{$systemExam->id}", [
            'name' => 'システム試験変更試行',
        ]);
        $updateSystemExamResponse->assertStatus(404); // ユーザーの所有物として見つからない

        $deleteSystemSubjectResponse = $this->deleteJson("/api/user/subject-areas/{$systemSubject->id}");
        $deleteSystemSubjectResponse->assertStatus(404); // ユーザーの所有物として見つからない
    }

    /** @test */
    public function cascade_deletion_protection_works()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 試験タイプを作成
        $examTypeResponse = $this->postJson('/api/user/exam-types', [
            'name' => '関連データありの試験',
            'description' => '削除保護テスト',
        ]);
        $examType = $examTypeResponse->json('exam_type');

        // 学習分野を作成
        $subjectResponse = $this->postJson('/api/user/subject-areas', [
            'exam_type_id' => $examType['id'],
            'name' => '関連データありの分野',
        ]);
        $subject = $subjectResponse->json('subject_area');

        // 学習セッションを作成（関連データ）
        $studySession = StudySession::create([
            'user_id' => $user->id,
            'subject_area_id' => $subject['id'],
            'started_at' => now(),
            'ended_at' => now()->addHour(),
            'duration_minutes' => 60,
            'study_comment' => 'テスト学習セッション',
        ]);

        // 学習履歴がある分野は削除できないことを確認
        $deleteSubjectResponse = $this->deleteJson("/api/user/subject-areas/{$subject['id']}");
        $deleteSubjectResponse->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'この学習分野には学習履歴が存在します。削除すると関連データも削除されます。',
            ]);

        // 関連する学習分野がある試験タイプは削除できないことを確認
        $deleteExamTypeResponse = $this->deleteJson("/api/user/exam-types/{$examType['id']}");
        $deleteExamTypeResponse->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'この試験タイプには学習履歴が存在します。削除すると関連データも削除されます。',
            ]);

        // データが削除されていないことを確認
        $this->assertDatabaseHas('exam_types', ['id' => $examType['id']]);
        $this->assertDatabaseHas('subject_areas', ['id' => $subject['id']]);
        $this->assertDatabaseHas('study_sessions', ['id' => $studySession->id]);
    }
}