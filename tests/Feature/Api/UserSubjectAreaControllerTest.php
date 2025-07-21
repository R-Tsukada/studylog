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

class UserSubjectAreaControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;


    /** @test */
    public function it_can_get_user_subject_areas_list()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // ユーザー固有の試験タイプを作成
        $userExam = ExamType::factory()->create([
            'user_id' => $user->id,
            'is_system' => false,
        ]);

        // システム標準の試験タイプを作成
        $systemExam = ExamType::factory()->create([
            'user_id' => null,
            'is_system' => true,
        ]);

        // ユーザー固有の学習分野を作成
        $userSubject = SubjectArea::factory()->create([
            'exam_type_id' => $userExam->id,
            'user_id' => $user->id,
            'name' => 'ユーザー固有分野',
            'is_system' => false,
        ]);

        // システム標準の学習分野を作成
        $systemSubject = SubjectArea::factory()->create([
            'exam_type_id' => $systemExam->id,
            'user_id' => null,
            'name' => 'システム標準分野',
            'is_system' => true,
        ]);

        // 他のユーザーの学習分野を作成（表示されないはず）
        $otherUser = User::factory()->create();
        $otherExam = ExamType::factory()->create([
            'user_id' => $otherUser->id,
            'is_system' => false,
        ]);
        SubjectArea::factory()->create([
            'exam_type_id' => $otherExam->id,
            'user_id' => $otherUser->id,
            'name' => '他のユーザーの分野',
            'is_system' => false,
        ]);

        $response = $this->getJson('/api/user/subject-areas');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ])
                ->assertJsonCount(2, 'subject_areas'); // ユーザー固有 + システム標準

        $responseData = $response->json('subject_areas');
        $subjectNames = collect($responseData)->pluck('name')->toArray();
        
        $this->assertContains('ユーザー固有分野', $subjectNames);
        $this->assertContains('システム標準分野', $subjectNames);
        $this->assertNotContains('他のユーザーの分野', $subjectNames);
    }

    /** @test */
    public function it_can_create_subject_area()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $examType = ExamType::factory()->create([
            'user_id' => $user->id,
            'is_system' => false,
        ]);

        $subjectData = [
            'exam_type_id' => $examType->id,
            'name' => 'データベース設計',
        ];

        $response = $this->postJson('/api/user/subject-areas', $subjectData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => '学習分野を作成しました',
                ])
                ->assertJsonStructure([
                    'subject_area' => [
                        'id', 'name', 'exam_type_id', 
                        'exam_type_name', 'is_system'
                    ]
                ]);

        $this->assertDatabaseHas('subject_areas', [
            'exam_type_id' => $examType->id,
            'name' => 'データベース設計',
            'user_id' => $user->id,
            'is_system' => false,
        ]);
    }

    /** @test */
    public function it_can_create_subject_area_for_system_exam_type()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // システム標準の試験タイプを作成
        $systemExam = ExamType::factory()->create([
            'user_id' => null,
            'is_system' => true,
        ]);

        $subjectData = [
            'exam_type_id' => $systemExam->id,
            'name' => 'アルゴリズム',
        ];

        $response = $this->postJson('/api/user/subject-areas', $subjectData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => '学習分野を作成しました',
                ]);

        $this->assertDatabaseHas('subject_areas', [
            'exam_type_id' => $systemExam->id,
            'name' => 'アルゴリズム',
            'user_id' => $user->id,
            'is_system' => false,
        ]);
    }

    /** @test */
    public function it_validates_subject_area_creation()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 必須項目が空の場合
        $response = $this->postJson('/api/user/subject-areas', []);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'バリデーションエラー',
                ])
                ->assertJsonValidationErrors(['exam_type_id', 'name']);

        // 存在しない試験タイプIDの場合
        $response = $this->postJson('/api/user/subject-areas', [
            'exam_type_id' => 99999,
            'name' => 'テスト分野',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['exam_type_id']);
    }

    /** @test */
    public function it_prevents_access_to_other_users_exam_types()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        Sanctum::actingAs($user1);

        // user2の試験タイプを作成
        $otherExam = ExamType::factory()->create([
            'user_id' => $user2->id,
            'is_system' => false,
        ]);

        $response = $this->postJson('/api/user/subject-areas', [
            'exam_type_id' => $otherExam->id,
            'name' => '不正アクセス試行',
        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => '指定された試験タイプが見つかりません',
                ]);
    }

    /** @test */
    public function it_prevents_duplicate_subject_areas_in_same_exam_type()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $examType = ExamType::factory()->create([
            'user_id' => $user->id,
            'is_system' => false,
        ]);

        // 最初の学習分野を作成
        SubjectArea::factory()->create([
            'exam_type_id' => $examType->id,
            'user_id' => $user->id,
            'name' => 'データベース',
            'is_system' => false,
        ]);

        // 同じ試験タイプ内で同じ名前の分野を作成試行
        $response = $this->postJson('/api/user/subject-areas', [
            'exam_type_id' => $examType->id,
            'name' => 'データベース',
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'この試験タイプには既に同じ名前の学習分野が存在します',
                ]);
    }

    /** @test */
    public function it_allows_same_subject_name_in_different_exam_types()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $examType1 = ExamType::factory()->create([
            'user_id' => $user->id,
            'is_system' => false,
        ]);

        $examType2 = ExamType::factory()->create([
            'user_id' => $user->id,
            'is_system' => false,
        ]);

        // 最初の試験タイプに学習分野を作成
        SubjectArea::factory()->create([
            'exam_type_id' => $examType1->id,
            'user_id' => $user->id,
            'name' => 'ネットワーク',
            'is_system' => false,
        ]);

        // 別の試験タイプに同じ名前の分野を作成（これは許可される）
        $response = $this->postJson('/api/user/subject-areas', [
            'exam_type_id' => $examType2->id,
            'name' => 'ネットワーク',
        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => '学習分野を作成しました',
                ]);
    }

    /** @test */
    public function it_can_update_subject_area()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $examType1 = ExamType::factory()->create([
            'user_id' => $user->id,
            'is_system' => false,
        ]);

        $examType2 = ExamType::factory()->create([
            'user_id' => $user->id,
            'is_system' => false,
        ]);

        $subjectArea = SubjectArea::factory()->create([
            'exam_type_id' => $examType1->id,
            'user_id' => $user->id,
            'name' => '旧分野名',
            'is_system' => false,
        ]);

        $updateData = [
            'exam_type_id' => $examType2->id,
            'name' => '新分野名',
        ];

        $response = $this->putJson("/api/user/subject-areas/{$subjectArea->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => '学習分野を更新しました',
                ]);

        $this->assertDatabaseHas('subject_areas', [
            'id' => $subjectArea->id,
            'exam_type_id' => $examType2->id,
            'name' => '新分野名',
        ]);
    }

    /** @test */
    public function it_cannot_update_other_users_subject_area()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        Sanctum::actingAs($user1);

        $examType = ExamType::factory()->create([
            'user_id' => $user2->id,
            'is_system' => false,
        ]);

        $subjectArea = SubjectArea::factory()->create([
            'exam_type_id' => $examType->id,
            'user_id' => $user2->id,
            'name' => 'ユーザー2の分野',
            'is_system' => false,
        ]);

        $response = $this->putJson("/api/user/subject-areas/{$subjectArea->id}", [
            'exam_type_id' => $examType->id,
            'name' => '攻撃試行',
        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => '指定された学習分野が見つかりません',
                ]);
    }

    /** @test */
    public function it_cannot_update_system_subject_areas()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $systemExam = ExamType::factory()->create([
            'user_id' => null,
            'is_system' => true,
        ]);

        $systemSubject = SubjectArea::factory()->create([
            'exam_type_id' => $systemExam->id,
            'user_id' => null,
            'name' => 'システム標準分野',
            'is_system' => true,
        ]);

        $response = $this->putJson("/api/user/subject-areas/{$systemSubject->id}", [
            'exam_type_id' => $systemExam->id,
            'name' => 'システム分野変更試行',
        ]);

        $response->assertStatus(404); // システム分野はユーザーの所有物として見つからない
    }

    /** @test */
    public function it_cannot_delete_subject_area_with_study_sessions()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $examType = ExamType::factory()->create([
            'user_id' => $user->id,
            'is_system' => false,
        ]);

        $subjectArea = SubjectArea::factory()->create([
            'exam_type_id' => $examType->id,
            'user_id' => $user->id,
            'name' => '学習履歴ありの分野',
            'is_system' => false,
        ]);

        // 学習セッションを作成（関連データがある状態）
        StudySession::factory()->create([
            'user_id' => $user->id,
            'subject_area_id' => $subjectArea->id,
        ]);

        $response = $this->deleteJson("/api/user/subject-areas/{$subjectArea->id}");

        $response->assertStatus(409)
                ->assertJson([
                    'success' => false,
                    'message' => 'この学習分野には学習履歴が存在します。削除すると関連データも削除されます。',
                ]);

        // データは削除されていないはず
        $this->assertDatabaseHas('subject_areas', [
            'id' => $subjectArea->id,
        ]);
    }

    /** @test */
    public function it_can_delete_subject_area_without_study_sessions()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $examType = ExamType::factory()->create([
            'user_id' => $user->id,
            'is_system' => false,
        ]);

        $subjectArea = SubjectArea::factory()->create([
            'exam_type_id' => $examType->id,
            'user_id' => $user->id,
            'name' => '学習履歴なしの分野',
            'is_system' => false,
        ]);

        $response = $this->deleteJson("/api/user/subject-areas/{$subjectArea->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ]);

        $this->assertDatabaseMissing('subject_areas', [
            'id' => $subjectArea->id,
        ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/user/subject-areas');
        $response->assertStatus(401);

        $response = $this->postJson('/api/user/subject-areas', [
            'exam_type_id' => 1,
            'name' => 'テスト分野',
        ]);
        $response->assertStatus(401);
    }
}