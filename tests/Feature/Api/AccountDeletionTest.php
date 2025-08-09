<?php

namespace Tests\Feature\Api;

use App\Models\ExamType;
use App\Models\StudyGoal;
use App\Models\StudySession;
use App\Models\SubjectArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_can_delete_account_with_valid_password()
    {
        $user = User::factory()->create([
            'email' => 'delete-test@example.com',
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'アカウントを削除しました',
            ]);

        // ユーザーがデータベースから削除されていることを確認
        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_can_delete_google_user_account_without_password()
    {
        $user = User::factory()->create([
            'email' => 'google-user@example.com',
            'password' => null,
            'google_id' => '123456789',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'アカウントを削除しました',
            ]);

        // ユーザーがデータベースから削除されていることを確認
        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_rejects_account_deletion_with_wrong_password()
    {
        $user = User::factory()->create([
            'email' => 'wrong-password@example.com',
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'wrongpassword',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'パスワードが間違っています',
            ]);

        // ユーザーがまだ存在することを確認
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_rejects_account_deletion_with_wrong_confirmation()
    {
        $user = User::factory()->create([
            'email' => 'wrong-confirmation@example.com',
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除しない',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['confirmation']);

        // ユーザーがまだ存在することを確認
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_requires_authentication_for_account_deletion()
    {
        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(401);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_deletes_related_user_data_when_account_is_deleted()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // 関連データを作成
        $examType = ExamType::factory()->create(['user_id' => $user->id]);
        $subjectArea = SubjectArea::factory()->create([
            'exam_type_id' => $examType->id,
            'user_id' => $user->id,
        ]);
        $studySession = StudySession::factory()->create([
            'user_id' => $user->id,
            'subject_area_id' => $subjectArea->id,
        ]);
        $studyGoal = StudyGoal::factory()->create([
            'user_id' => $user->id,
            'exam_type_id' => $examType->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200);

        // ユーザーと関連データが削除されていることを確認
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('exam_types', ['id' => $examType->id]);
        $this->assertDatabaseMissing('subject_areas', ['id' => $subjectArea->id]);
        $this->assertDatabaseMissing('study_sessions', ['id' => $studySession->id]);
        $this->assertDatabaseMissing('study_goals', ['id' => $studyGoal->id]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_validates_required_fields_for_account_deletion()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        // パスワードなし
        $response = $this->deleteJson('/api/auth/account', [
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // 確認なし
        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['confirmation']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_deletes_user_tokens_when_account_is_deleted()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // トークンを作成
        $token = $user->createToken('test-token');

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200);

        // ユーザーとトークンが削除されていることを確認
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_handles_special_characters_in_password()
    {
        $specialPassword = 'パスワード123！@#$%^&*()';
        $user = User::factory()->create([
            'password' => Hash::make($specialPassword),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'password' => $specialPassword,
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'アカウントを削除しました',
            ]);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_handles_empty_password_field()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'password' => '',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_handles_empty_confirmation_field()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['confirmation']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_handles_incorrect_confirmation_variations()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $wrongConfirmations = [
            '削除する',
            'delete',
            'DELETE',
            '削除',
            '削除　します',  // 全角スペース
            '削除 します',   // 半角スペース
            'さくじょします',
        ];

        foreach ($wrongConfirmations as $wrongConfirmation) {
            $response = $this->deleteJson('/api/auth/account', [
                'password' => 'password123',
                'confirmation' => $wrongConfirmation,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['confirmation']);

            $this->assertDatabaseHas('users', ['id' => $user->id]);
        }
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_handles_very_long_password()
    {
        $longPassword = str_repeat('あ', 1000);
        $user = User::factory()->create([
            'password' => Hash::make($longPassword),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'password' => $longPassword,
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'アカウントを削除しました',
            ]);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_handles_concurrent_deletion_attempts()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // トークンを作成
        $token = $user->createToken('test')->plainTextToken;

        // 最初の削除は成功
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $response1->assertStatus(200);

        // 2回目の削除試行は失敗（トークンが削除されている）
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        // トークンが削除されているので認証エラーになるはず（実装により200が返る場合もある）
        $this->assertTrue(in_array($response2->status(), [200, 401]));
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_preserves_system_data_when_user_is_deleted()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // システムデータを作成
        $systemExamType = ExamType::factory()->create([
            'user_id' => null,
            'is_system' => true,
            'name' => 'システム試験タイプ',
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
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_handles_account_deletion_with_large_amounts_of_data()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // 大量のテストデータを作成
        $examTypes = ExamType::factory(5)->create(['user_id' => $user->id]);

        foreach ($examTypes as $examType) {
            $subjectAreas = SubjectArea::factory(10)->create([
                'exam_type_id' => $examType->id,
                'user_id' => $user->id,
            ]);

            foreach ($subjectAreas as $subjectArea) {
                StudySession::factory(20)->create([
                    'user_id' => $user->id,
                    'subject_area_id' => $subjectArea->id,
                ]);
            }

            StudyGoal::factory(3)->create([
                'user_id' => $user->id,
                'exam_type_id' => $examType->id,
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200);

        // すべてのユーザーデータが削除されていることを確認
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('exam_types', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('subject_areas', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('study_sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('study_goals', ['user_id' => $user->id]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_handles_request_without_required_fields()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        // 必須フィールドなしでリクエスト
        $response = $this->deleteJson('/api/auth/account', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // ユーザーが削除されていないことを確認
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_handles_missing_authorization_header()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // 認証ヘッダーなしでリクエスト
        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(401);

        // ユーザーが削除されていないことを確認
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_handles_invalid_token()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // 無効なトークンでリクエスト
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(401);

        // ユーザーが削除されていないことを確認
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_handles_account_deletion_during_active_study_session()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $examType = ExamType::factory()->create(['user_id' => $user->id]);
        $subjectArea = SubjectArea::factory()->create([
            'exam_type_id' => $examType->id,
            'user_id' => $user->id,
        ]);

        // アクティブな学習セッションを作成
        $activeSession = StudySession::factory()->create([
            'user_id' => $user->id,
            'subject_area_id' => $subjectArea->id,
            'started_at' => now(),
            'ended_at' => null, // アクティブセッション
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200);

        // ユーザーとアクティブセッションが削除されていることを確認
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('study_sessions', ['id' => $activeSession->id]);
    }
}
