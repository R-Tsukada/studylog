<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_can_register_a_new_user()
    {
        $userData = [
            'nickname' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'ユーザー登録が完了しました',
            ])
            ->assertJsonStructure([
                'user' => [
                    'id', 'nickname', 'email', 'avatar_url', 'is_google_user',
                ],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'nickname' => 'テストユーザー',
            'email' => 'test@example.com',
            'google_id' => null,
        ]);

        // パスワードがハッシュ化されていることを確認
        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue(Hash::check('Password123!', $user->password));
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_validates_user_registration()
    {
        // 必須項目が空の場合
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'バリデーションエラー',
            ])
            ->assertJsonValidationErrors(['nickname', 'email', 'password']);

        // 不正なメールアドレス
        $response = $this->postJson('/api/auth/register', [
            'nickname' => 'テストユーザー',
            'email' => 'invalid-email',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // パスワード確認が一致しない
        $response = $this->postJson('/api/auth/register', [
            'nickname' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'different_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // 短すぎるパスワード
        $response = $this->postJson('/api/auth/register', [
            'nickname' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => '12345',
            'password_confirmation' => '12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_prevents_duplicate_email_registration()
    {
        // 既存ユーザーを作成
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'nickname' => 'テストユーザー',
            'email' => 'existing@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'ログインしました',
            ])
            ->assertJsonStructure([
                'user' => [
                    'id', 'nickname', 'email', 'avatar_url', 'is_google_user',
                ],
                'token',
            ]);

        // 返されたユーザー情報が正しいことを確認
        $responseData = $response->json();
        $this->assertEquals($user->id, $responseData['user']['id']);
        $this->assertEquals($user->email, $responseData['user']['email']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_rejects_invalid_login_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        // 間違ったパスワード
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'メールアドレスまたはパスワードが間違っています',
            ]);

        // 存在しないメールアドレス
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'メールアドレスまたはパスワードが間違っています',
            ]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_validates_login_input()
    {
        // 必須項目が空の場合
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'バリデーションエラー',
            ])
            ->assertJsonValidationErrors(['email', 'password']);

        // 不正なメールアドレス
        $response = $this->postJson('/api/auth/login', [
            'email' => 'invalid-email',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_can_get_authenticated_user_info()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'user' => [
                    'id', 'nickname', 'email', 'avatar_url', 'is_google_user',
                ],
            ]);

        $responseData = $response->json();
        $this->assertEquals($user->id, $responseData['user']['id']);
        $this->assertEquals($user->email, $responseData['user']['email']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_requires_authentication_for_user_endpoint()
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_can_logout_authenticated_user()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'ログアウトしました',
            ]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_can_update_user_profile()
    {
        $user = User::factory()->create([
            'nickname' => '旧名前',
            'email' => 'old@example.com',
        ]);
        Sanctum::actingAs($user);

        $updateData = [
            'nickname' => '新名前',
            'email' => 'new@example.com',
        ];

        $response = $this->putJson('/api/auth/profile', $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'プロフィールを更新しました',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'nickname' => '新名前',
            'email' => 'new@example.com',
        ]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_validates_profile_update()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 空のリクエストの場合（部分更新なので成功する）
        $response = $this->putJson('/api/auth/profile', []);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'プロフィールを更新しました',
            ]);

        // 不正なメールアドレス
        $response = $this->putJson('/api/auth/profile', [
            'nickname' => '新名前',
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_prevents_profile_update_with_existing_email()
    {
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        Sanctum::actingAs($user1);

        // user2のメールアドレスに変更を試行
        $response = $this->putJson('/api/auth/profile', [
            'nickname' => '新名前',
            'email' => 'user2@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_allows_profile_update_with_same_email()
    {
        $user = User::factory()->create([
            'nickname' => '旧名前',
            'email' => 'user@example.com',
        ]);
        Sanctum::actingAs($user);

        // 同じメールアドレスで名前だけ変更
        $response = $this->putJson('/api/auth/profile', [
            'nickname' => '新名前',
            'email' => 'user@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'プロフィールを更新しました',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'nickname' => '新名前',
            'email' => 'user@example.com',
        ]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_generates_correct_avatar_url()
    {
        $userData = [
            'nickname' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201);

        $responseData = $response->json();
        $avatarUrl = $responseData['user']['avatar_url'];

        // Gravatarの形式であることを確認
        $this->assertStringContainsString('gravatar.com', $avatarUrl);
        $this->assertStringContainsString('identicon', $avatarUrl);
        $this->assertStringContainsString('s=100', $avatarUrl);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function tokens_are_different_for_each_login()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $response1 = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response2 = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $token1 = $response1->json('token');
        $token2 = $response2->json('token');

        $this->assertNotEquals($token1, $token2);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_validates_nickname_field_length_in_registration()
    {
        // 長すぎるニックネーム（255文字超過）
        $longNickname = str_repeat('あ', 256);

        $response = $this->postJson('/api/auth/register', [
            'nickname' => $longNickname,
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_allows_valid_japanese_nicknames()
    {
        $testCases = [
            'ひらがな',
            'カタカナ',
            '漢字',
            'English123',
            'ミックス名前123',
        ];

        foreach ($testCases as $nickname) {
            $response = $this->postJson('/api/auth/register', [
                'nickname' => $nickname,
                'email' => "test{$nickname}@example.com",
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

            $response->assertStatus(201, "Failed for nickname: {$nickname}");

            $this->assertDatabaseHas('users', [
                'nickname' => $nickname,
                'email' => "test{$nickname}@example.com",
            ]);
        }
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_handles_nickname_migration_properly()
    {
        // マイグレーションテスト用のケース
        $userData = [
            'nickname' => 'マイグレーション後ユーザー',
            'email' => 'migration@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201);

        // nameフィールドがデータベースに存在しないことを確認
        $user = User::where('email', 'migration@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('マイグレーション後ユーザー', $user->nickname);

        // nameフィールドがAPIレスポンスに含まれないことを確認
        $responseData = $response->json();
        $this->assertArrayNotHasKey('name', $responseData['user']);
        $this->assertArrayHasKey('nickname', $responseData['user']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_handles_authentication_flow_with_nickname()
    {
        // 登録
        $userData = [
            'nickname' => '認証フローテスト',
            'email' => 'auth-flow@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $registerResponse = $this->postJson('/api/auth/register', $userData);
        $registerResponse->assertStatus(201);

        $registerData = $registerResponse->json();
        $this->assertEquals('認証フローテスト', $registerData['user']['nickname']);

        // ログイン
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'auth-flow@example.com',
            'password' => 'Password123!',
        ]);

        $loginResponse->assertStatus(200);
        $loginData = $loginResponse->json();
        $this->assertEquals('認証フローテスト', $loginData['user']['nickname']);

        // プロフィール更新
        $token = $loginData['token'];
        $profileResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson('/api/auth/profile', [
            'nickname' => '更新後ニックネーム',
        ]);

        $profileResponse->assertStatus(200);

        // 更新されたプロフィール確認
        $userInfoResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/user');

        $userInfoResponse->assertStatus(200);
        $userInfoData = $userInfoResponse->json();
        $this->assertEquals('更新後ニックネーム', $userInfoData['user']['nickname']);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_handles_token_management()
    {
        $user = User::factory()->create([
            'email' => 'token-test@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        // ログインしてトークンを作成
        $response = $this->postJson('/api/auth/login', [
            'email' => 'token-test@example.com',
            'password' => 'Password123!',
        ]);

        $token = $response->json('token');

        // トークンが有効
        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/user')
            ->assertStatus(200);

        // ログアウト
        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/auth/logout')
            ->assertStatus(200);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_validates_nickname_character_restrictions()
    {
        // 無効な文字を含むニックネーム
        $invalidNickname = '<script>alert("xss")</script>';

        $response = $this->postJson('/api/auth/register', [
            'nickname' => $invalidNickname,
            'email' => 'security-test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        // 新しいバリデーションルールにより、無効な文字を含むニックネームは拒否される
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);

        // データベースにユーザーが作成されていないことを確認
        $this->assertDatabaseMissing('users', [
            'email' => 'security-test@example.com',
        ]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_validates_enhanced_email_domain_restrictions()
    {
        // 許可されていないドメイン
        $invalidDomains = [
            'test@gmail.co',
            'test@yahoo.co.uk',
            'test@example.info',
            'test@test.xyz',
        ];

        foreach ($invalidDomains as $email) {
            $response = $this->postJson('/api/auth/register', [
                'nickname' => 'テストユーザー',
                'email' => $email,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

            $response->assertStatus(422);
            $responseData = $response->json();
            $this->assertArrayHasKey('errors', $responseData);
            $this->assertArrayHasKey('email', $responseData['errors'], "Failed for email: {$email}");
        }

        // 許可されているドメイン
        $validDomains = [
            'test@example.com',
            'test@example.net',
            'test@example.org',
            'test@example.jp',
            'test@university.edu',
            'test@agency.gov',
        ];

        foreach ($validDomains as $email) {
            $response = $this->postJson('/api/auth/register', [
                'nickname' => 'テストユーザー'.rand(1000, 9999),
                'email' => $email,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

            $response->assertStatus(201, "Failed for email: {$email}");
        }
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_validates_enhanced_password_complexity()
    {
        // 英字がないパスワード
        $response = $this->postJson('/api/auth/register', [
            'nickname' => 'テストユーザー',
            'email' => 'test1@example.com',
            'password' => '12345678!',
            'password_confirmation' => '12345678!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // 数字がないパスワード
        $response = $this->postJson('/api/auth/register', [
            'nickname' => 'テストユーザー',
            'email' => 'test2@example.com',
            'password' => 'Password!',
            'password_confirmation' => 'Password!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // 記号がないパスワード
        $response = $this->postJson('/api/auth/register', [
            'nickname' => 'テストユーザー',
            'email' => 'test3@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // 全ての条件を満たすパスワード
        $response = $this->postJson('/api/auth/register', [
            'nickname' => 'テストユーザー',
            'email' => 'test4@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_validates_enhanced_nickname_rules()
    {
        // 短すぎるニックネーム（2文字未満）
        $response = $this->postJson('/api/auth/register', [
            'nickname' => 'あ',
            'email' => 'test1@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);

        // 長すぎるニックネーム（50文字超過）
        $longNickname = str_repeat('あ', 51);
        $response = $this->postJson('/api/auth/register', [
            'nickname' => $longNickname,
            'email' => 'test2@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);

        // 無効な文字を含むニックネーム
        $invalidNicknames = [
            'test@user' => 'test1@example.com',
            'test user' => 'test2@example.com',
            'test#user' => 'test3@example.com',
            'test-user' => 'test4@example.com',
        ];

        foreach ($invalidNicknames as $nickname => $email) {
            $response = $this->postJson('/api/auth/register', [
                'nickname' => $nickname,
                'email' => $email,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

            $response->assertStatus(422);
            $responseData = $response->json();
            $this->assertArrayHasKey('errors', $responseData);
            $this->assertArrayHasKey('nickname', $responseData['errors'], "Failed for nickname: {$nickname}");
        }

        // 有効なニックネーム
        $validNicknames = [
            'あいうえお',
            'テストユーザー',
            '山田太郎',
            'TestUser123',
            'ユーザー123',
        ];

        $validEmails = [
            'test5@example.com',
            'test6@example.com',
            'test7@example.com',
            'test8@example.com',
            'test9@example.com',
        ];

        foreach ($validNicknames as $index => $nickname) {
            $response = $this->postJson('/api/auth/register', [
                'nickname' => $nickname,
                'email' => $validEmails[$index],
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

            $response->assertStatus(201, "Failed for nickname: {$nickname}");
        }
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function it_validates_enhanced_login_rules()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        // 短すぎるパスワードでログイン試行
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => '1234567',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // 正しい形式でログイン
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200);
    }
}
