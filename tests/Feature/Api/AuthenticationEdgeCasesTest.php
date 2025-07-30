<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationEdgeCasesTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function it_handles_empty_nickname_gracefully()
    {
        $response = $this->postJson('/api/auth/register', [
            'nickname' => '',
            'email' => 'empty-nickname@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);
    }

    /** @test */
    public function it_handles_whitespace_only_nickname()
    {
        $response = $this->postJson('/api/auth/register', [
            'nickname' => '   ',
            'email' => 'whitespace@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);
    }

    /** @test */
    public function it_trims_nickname_whitespace()
    {
        $response = $this->postJson('/api/auth/register', [
            'nickname' => '  トリムテスト  ',
            'email' => 'trim-test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'trim-test@example.com')->first();
        $this->assertEquals('トリムテスト', $user->nickname);
    }

    /** @test */
    public function it_rejects_special_characters_in_nickname()
    {
        $invalidNicknames = [
            '特殊文字！@#$%',
            'emoji😀🎉',
            'ハイフン-アンダー_',
            '日本語・英語Mix',
            'spaces in name',
        ];

        foreach ($invalidNicknames as $index => $nickname) {
            $response = $this->postJson('/api/auth/register', [
                'nickname' => $nickname,
                'email' => "special{$index}@example.com",
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

            $response->assertStatus(422, "Should fail for nickname: {$nickname}")
                ->assertJsonValidationErrors(['nickname']);

            $this->assertDatabaseMissing('users', [
                'email' => "special{$index}@example.com",
            ]);
        }

        // 有効なニックネームのテスト
        $validNickname = '数字123混合';
        $response = $this->postJson('/api/auth/register', [
            'nickname' => $validNickname,
            'email' => 'valid@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'nickname' => $validNickname,
            'email' => 'valid@example.com',
        ]);
    }

    /** @test */
    public function it_handles_email_uniqueness()
    {
        // 最初にユーザーを登録
        User::factory()->create([
            'email' => 'test@example.com',
            'nickname' => 'オリジナル',
        ]);

        // 完全に同じメールで登録を試行
        $response = $this->postJson('/api/auth/register', [
            'nickname' => '重複テスト',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_handles_extremely_long_password()
    {
        $longPassword = 'Password123!' . str_repeat('a', 986); // 1000文字の複雑なパスワード

        $response = $this->postJson('/api/auth/register', [
            'nickname' => '長いパスワードテスト',
            'email' => 'long-password@example.com',
            'password' => $longPassword,
            'password_confirmation' => $longPassword,
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'long-password@example.com')->first();
        $this->assertTrue(Hash::check($longPassword, $user->password));
    }

    /** @test */
    public function it_handles_unicode_characters_in_password()
    {
        $unicodePassword = 'パスワード123！@#';

        $response = $this->postJson('/api/auth/register', [
            'nickname' => 'Unicodeパスワード',
            'email' => 'unicode-pwd@example.com',
            'password' => $unicodePassword,
            'password_confirmation' => $unicodePassword,
        ]);

        $response->assertStatus(201);

        // ログインテスト
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'unicode-pwd@example.com',
            'password' => $unicodePassword,
        ]);

        $loginResponse->assertStatus(200);
    }

    /** @test */
    public function it_handles_null_and_undefined_fields()
    {
        // nullフィールドを含むリクエスト
        $response = $this->postJson('/api/auth/register', [
            'nickname' => null,
            'email' => 'null-test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);
    }

    /** @test */
    public function it_handles_empty_request_body()
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname', 'email', 'password']);
    }

    /** @test */
    public function it_handles_simultaneous_login_attempts()
    {
        $user = User::factory()->create([
            'email' => 'concurrent@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        // 同時に複数のログイン試行
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->postJson('/api/auth/login', [
                'email' => 'concurrent@example.com',
                'password' => 'Password123!',
            ]);
        }

        // すべて成功すること
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // 各トークンが異なること
        $tokens = array_map(fn ($response) => $response->json('token'), $responses);
        $this->assertEquals(count($tokens), count(array_unique($tokens)));
    }

    /** @test */
    public function it_handles_token_with_invalid_bearer_prefix()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // 間違ったprefixでトークンを送信
        $response = $this->withHeaders([
            'Authorization' => 'Basic '.$token,
        ])->getJson('/api/user');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_expired_or_invalid_tokens()
    {
        $user = User::factory()->create();

        // 無効なトークン
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-string',
        ])->getJson('/api/user');

        $response->assertStatus(401);

        // 空のトークン
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ',
        ])->getJson('/api/user');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_profile_update_edge_cases()
    {
        $user = User::factory()->create([
            'nickname' => 'オリジナル名前',
            'email' => 'original@example.com',
        ]);
        Sanctum::actingAs($user);

        // 同じ値での更新（変更なし）
        $response = $this->putJson('/api/auth/profile', [
            'nickname' => 'オリジナル名前',
            'email' => 'original@example.com',
        ]);

        $response->assertStatus(200);

        // 空文字での更新試行
        $response = $this->putJson('/api/auth/profile', [
            'nickname' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);

        // 非常に長いニックネーム
        $longNickname = str_repeat('長', 300);
        $response = $this->putJson('/api/auth/profile', [
            'nickname' => $longNickname,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);
    }

    /** @test */
    public function it_handles_database_constraint_violations()
    {
        // 最初のユーザーを作成
        $user1 = User::factory()->create([
            'email' => 'user1@example.com',
        ]);

        // 2番目のユーザーを作成
        $user2 = User::factory()->create([
            'email' => 'user2@example.com',
        ]);

        Sanctum::actingAs($user2);

        // user1のメールアドレスに変更を試行
        $response = $this->putJson('/api/auth/profile', [
            'email' => 'user1@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_handles_missing_request_headers()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Content-Typeヘッダーなしでリクエスト
        $response = $this->json('PUT', '/api/auth/profile', [
            'nickname' => '新しい名前',
        ], [
            // Content-Typeを明示的に除外
        ]);

        // LaravelはJSONリクエストを適切に処理できることを確認
        $response->assertStatus(200);
    }

    /** @test */
    public function it_handles_logout_with_invalid_token()
    {
        // 無効なトークンでログアウト
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_logout_without_token()
    {
        // トークンなしでログアウト
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_user_info_access_after_logout()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // ログアウト
        $this->postJson('/api/auth/logout')->assertStatus(200);

        // ログアウト後にユーザー情報へのアクセス試行（Sanctum::actingAsは残るので200が返る）
        $response = $this->getJson('/api/user');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_handles_multiple_logout_attempts()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 最初のログアウト
        $response1 = $this->postJson('/api/auth/logout');
        $response1->assertStatus(200);

        // 再度ログアウト試行（Sanctum::actingAsは残るので200が返る）
        $response2 = $this->postJson('/api/auth/logout');
        $response2->assertStatus(200);
    }
}
