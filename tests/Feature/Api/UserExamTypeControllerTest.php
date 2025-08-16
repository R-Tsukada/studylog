<?php

namespace Tests\Feature\Api;

use App\Models\ExamType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserExamTypeControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    #[Test]
    public function it_can_get_user_exam_types_list()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // ユーザー固有の試験タイプを作成
        $userExam = ExamType::factory()->create([
            'user_id' => $user->id,
            'name' => 'ユーザー固有の試験',
            'is_system' => false,
        ]);

        // システム標準の試験タイプを作成
        $systemExam = ExamType::factory()->create([
            'user_id' => null,
            'name' => 'システム標準試験',
            'is_system' => true,
        ]);

        // 他のユーザーの試験タイプを作成（表示されないはず）
        $otherUser = User::factory()->create();
        ExamType::factory()->create([
            'user_id' => $otherUser->id,
            'name' => '他のユーザーの試験',
            'is_system' => false,
        ]);

        $response = $this->getJson('/api/user/exam-types');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(2, 'exam_types'); // ユーザー固有 + システム標準

        $responseData = $response->json('exam_types');
        $examNames = collect($responseData)->pluck('name')->toArray();

        $this->assertContains('ユーザー固有の試験', $examNames);
        $this->assertContains('システム標準試験', $examNames);
        $this->assertNotContains('他のユーザーの試験', $examNames);
    }

    /** @test */
    #[Test]
    public function it_can_create_exam_type()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $examData = [
            'name' => 'AWS Solutions Architect',
            'description' => 'Amazon Web Services認定試験',
            'exam_date' => '2025-06-15',
            'exam_notes' => '学習計画：毎日2時間',
            'color' => '#FF9900',
        ];

        $response = $this->postJson('/api/user/exam-types', $examData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '試験タイプを作成しました',
            ])
            ->assertJsonStructure([
                'exam_type' => [
                    'id', 'code', 'name', 'description',
                    'exam_date', 'exam_notes', 'color',
                    'user_id', 'is_system',
                ],
            ]);

        $this->assertDatabaseHas('exam_types', [
            'name' => 'AWS Solutions Architect',
            'description' => 'Amazon Web Services認定試験',
            'user_id' => $user->id,
            'is_system' => false,
            'color' => '#FF9900',
        ]);

        // コードが自動生成されていることを確認
        $examType = ExamType::where('name', 'AWS Solutions Architect')->first();
        $this->assertNotNull($examType->code);
        $this->assertStringContainsString('awssolutio', $examType->code);
    }

    /** @test */
    #[Test]
    public function it_validates_exam_type_creation()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 必須項目が空の場合
        $response = $this->postJson('/api/user/exam-types', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'バリデーションエラー',
            ])
            ->assertJsonValidationErrors(['name']);

        // 不正なカラーコードの場合
        $response = $this->postJson('/api/user/exam-types', [
            'name' => 'テスト試験',
            'color' => 'invalid-color',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['color']);

        // 不正な日付の場合
        $response = $this->postJson('/api/user/exam-types', [
            'name' => 'テスト試験',
            'exam_date' => 'invalid-date',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exam_date']);
    }

    /** @test */
    #[Test]
    public function it_prevents_duplicate_exam_type_names()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 最初の試験タイプを作成
        ExamType::factory()->create([
            'user_id' => $user->id,
            'name' => '基本情報技術者試験',
            'is_system' => false,
        ]);

        // 同じ名前で作成を試行
        $response = $this->postJson('/api/user/exam-types', [
            'name' => '基本情報技術者試験',
            'description' => '重複テスト',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'この名前の試験タイプは既に存在します',
            ]);
    }

    /** @test */
    #[Test]
    public function it_can_update_exam_type()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $examType = ExamType::factory()->create([
            'user_id' => $user->id,
            'name' => '旧試験名',
            'description' => '旧説明',
            'is_system' => false,
        ]);

        $updateData = [
            'name' => '新試験名',
            'description' => '新説明',
            'exam_date' => '2025-07-20',
            'exam_notes' => '新しいメモ',
            'color' => '#00FF00',
        ];

        $response = $this->putJson("/api/user/exam-types/{$examType->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '試験タイプを更新しました',
            ]);

        $this->assertDatabaseHas('exam_types', [
            'id' => $examType->id,
            'name' => '新試験名',
            'description' => '新説明',
            'color' => '#00FF00',
        ]);
    }

    /** @test */
    #[Test]
    public function it_cannot_update_other_users_exam_type()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Sanctum::actingAs($user1);

        $examType = ExamType::factory()->create([
            'user_id' => $user2->id,
            'name' => 'ユーザー2の試験',
            'is_system' => false,
        ]);

        $response = $this->putJson("/api/user/exam-types/{$examType->id}", [
            'name' => '攻撃試行',
            'description' => '他のユーザーの試験を変更',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => '指定された試験タイプが見つかりません',
            ]);
    }

    /** @test */
    #[Test]
    public function it_cannot_update_system_exam_types()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $systemExam = ExamType::factory()->create([
            'user_id' => null,
            'name' => 'システム標準試験',
            'is_system' => true,
        ]);

        $response = $this->putJson("/api/user/exam-types/{$systemExam->id}", [
            'name' => 'システム試験変更試行',
            'description' => 'システム標準を変更',
        ]);

        $response->assertStatus(404); // システム試験はユーザーの所有物として見つからない
    }

    /** @test */
    #[Test]
    public function it_can_delete_exam_type()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $examType = ExamType::factory()->create([
            'user_id' => $user->id,
            'name' => '削除対象試験',
            'is_system' => false,
        ]);

        $response = $this->deleteJson("/api/user/exam-types/{$examType->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('exam_types', [
            'id' => $examType->id,
        ]);
    }

    /** @test */
    #[Test]
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/user/exam-types');
        $response->assertStatus(401);

        $response = $this->postJson('/api/user/exam-types', [
            'name' => 'テスト試験',
        ]);
        $response->assertStatus(401);
    }

    /** @test */
    #[Test]
    public function it_generates_unique_codes_for_multiple_exams()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $examData1 = [
            'name' => 'AWS Solutions Architect',
            'description' => 'AWS認定試験1',
        ];

        $examData2 = [
            'name' => 'AWS Solutions Architect',
            'description' => 'AWS認定試験2',
        ];

        // 最初の試験は成功
        $response1 = $this->postJson('/api/user/exam-types', $examData1);
        $response1->assertStatus(201);

        // 同じ名前の試験は失敗するはず（重複チェック）
        $response2 = $this->postJson('/api/user/exam-types', $examData2);
        $response2->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'この名前の試験タイプは既に存在します',
            ]);
    }
}
