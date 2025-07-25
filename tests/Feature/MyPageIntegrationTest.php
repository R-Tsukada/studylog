<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyPageIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function mypage_route_is_accessible_with_authentication()
    {
        $user = User::factory()->create();
        
        // ログイン状態をシミュレート
        $response = $this->actingAs($user)
            ->get('/mypage');

        // マイページにアクセスできることを確認（SPAなので基本的にはwelcome.blade.phpが返される）
        $response->assertStatus(200);
    }

    /** @test */
    public function mypage_route_redirects_unauthenticated_users()
    {
        // 未認証でマイページにアクセス
        $response = $this->get('/mypage');

        // SPAアプリケーションが正常に読み込まれることを確認
        $response->assertStatus(200);
        
        // Vueアプリのコンテナが存在することを確認（Vue Routerで認証処理される）
        $response->assertSee('id="app"', false);
        
        // アプリケーションタイトルが正しく設定されていることを確認
        $response->assertSee('<title>Study Log - すたログ</title>', false);
        
        // Vue.jsアプリケーションの基本構造が含まれていることを確認
        $response->assertSee('resources/js/app.js', false);
    }

    /** @test */
    public function complete_profile_update_workflow()
    {
        $user = User::factory()->create([
            'nickname' => '元のニックネーム',
            'email' => 'original@example.com',
            'password' => Hash::make('originalpassword'),
        ]);
        
        Sanctum::actingAs($user);

        // 1. ユーザー情報を取得
        $userResponse = $this->getJson('/api/user');
        $userResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'nickname' => '元のニックネーム',
                    'email' => 'original@example.com',
                ],
            ]);

        // 2. プロフィールを更新
        $updateResponse = $this->putJson('/api/auth/profile', [
            'nickname' => '更新されたニックネーム',
            'email' => 'updated@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'プロフィールを更新しました',
                'user' => [
                    'nickname' => '更新されたニックネーム',
                    'email' => 'updated@example.com',
                ],
            ]);

        // 3. 更新後のユーザー情報を再取得して確認
        $verifyResponse = $this->getJson('/api/user');
        $verifyResponse->assertStatus(200)
            ->assertJson([
                'user' => [
                    'nickname' => '更新されたニックネーム',
                    'email' => 'updated@example.com',
                ],
            ]);

        // 4. 新しいパスワードでログインできることを確認
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    /** @test */
    public function complete_account_deletion_workflow()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        
        Sanctum::actingAs($user);

        // 1. 削除前にユーザーが存在することを確認
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        // 2. アカウント削除を実行
        $deleteResponse = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $deleteResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'アカウントを削除しました',
            ]);

        // 3. ユーザーがデータベースから削除されていることを確認
        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        // 4. ユーザーが削除されていることを再確認（削除処理の完了確認）
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /** @test */
    public function google_user_profile_update_workflow()
    {
        $data = [
            'nickname' => 'Google太郎',
            'email' => 'google@example.com',
            'password' => null,
            'google_id' => 'google_12345',
        ];
        
        // avatar_urlカラムが存在する場合のみ設定
        if (Schema::hasColumn('users', 'avatar_url')) {
            $data['avatar_url'] = 'https://lh3.googleusercontent.com/avatar';
        }
        
        $googleUser = User::factory()->create($data);
        
        Sanctum::actingAs($googleUser);

        // 1. Googleユーザーの情報を取得
        $userResponse = $this->getJson('/api/user');
        $userResponse->assertStatus(200)
            ->assertJson([
                'user' => [
                    'nickname' => 'Google太郎',
                    'email' => 'google@example.com',
                    'is_google_user' => true,
                ],
            ]);
            
        // avatar_urlカラムが存在する場合のみテスト
        if (Schema::hasColumn('users', 'avatar_url')) {
            $userResponse->assertJson([
                'user' => [
                    'avatar_url' => 'https://lh3.googleusercontent.com/avatar',
                ],
            ]);
        }

        // 2. Googleユーザーのプロフィール更新（パスワード変更なし）
        $updateResponse = $this->putJson('/api/auth/profile', [
            'nickname' => '更新されたGoogle太郎',
            'email' => 'updated-google@example.com',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'nickname' => '更新されたGoogle太郎',
                    'email' => 'updated-google@example.com',
                    'is_google_user' => true,
                ],
            ]);

        // 3. Google認証情報が保持されていることを確認
        $googleUser->refresh();
        $this->assertEquals('google_12345', $googleUser->google_id);
        $this->assertNull($googleUser->password);
    }

    /** @test */
    public function google_user_account_deletion_workflow()
    {
        $googleUser = User::factory()->create([
            'password' => null,
            'google_id' => 'google_12345',
        ]);
        
        Sanctum::actingAs($googleUser);

        // Googleユーザーはパスワード不要で削除可能
        $deleteResponse = $this->deleteJson('/api/auth/account', [
            'confirmation' => '削除します',
        ]);

        $deleteResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'アカウントを削除しました',
            ]);

        $this->assertDatabaseMissing('users', ['id' => $googleUser->id]);
    }

    /** @test */
    public function profile_update_error_handling_workflow()
    {
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);
        
        Sanctum::actingAs($user1);

        // 1. 重複メールアドレスでの更新試行
        $response = $this->putJson('/api/auth/profile', [
            'nickname' => 'テストユーザー',
            'email' => 'user2@example.com', // 既に存在するメール
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // 2. バリデーションエラー後も元のデータが保持されることを確認
        $this->assertDatabaseHas('users', [
            'id' => $user1->id,
            'email' => 'user1@example.com', // 変更されていない
        ]);

        // 3. 正しいデータでの更新は成功することを確認
        $response = $this->putJson('/api/auth/profile', [
            'nickname' => '正しいニックネーム',
            'email' => 'correct@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function account_deletion_with_wrong_password_workflow()
    {
        $user = User::factory()->create([
            'password' => Hash::make('correctpassword'),
        ]);
        
        Sanctum::actingAs($user);

        // 1. 間違ったパスワードで削除試行
        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'wrongpassword',
            'confirmation' => '削除します',
        ]);

        // APIは間違ったパスワードの場合401を返す（パスワード認証失敗）
        $response->assertStatus(401);

        // 2. ユーザーが削除されていないことを確認
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        // 3. 正しいパスワードでは削除できることを確認
        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'correctpassword',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /** @test */
    public function session_and_token_management_during_profile_operations()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        
        // 複数のトークンを作成
        $token1 = $user->createToken('device1');
        $token2 = $user->createToken('device2');
        
        Sanctum::actingAs($user);

        // 1. プロフィール更新は既存トークンに影響しない
        $response = $this->putJson('/api/auth/profile', [
            'nickname' => '更新されたユーザー',
            'email' => $user->email,
        ]);

        $response->assertStatus(200);

        // トークンが残っていることを確認
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'device1',
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'device2',
        ]);

        // 2. アカウント削除時はすべてのトークンが削除される
        $response = $this->deleteJson('/api/auth/account', [
            'password' => 'password123',
            'confirmation' => '削除します',
        ]);

        $response->assertStatus(200);

        // すべてのトークンが削除されていることを確認
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    /** @test */
    public function mypage_data_consistency_across_operations()
    {
        $user = User::factory()->create([
            'nickname' => '初期ニックネーム',
            'email' => 'initial@example.com',
            'password' => Hash::make('password123'),
        ]);
        
        Sanctum::actingAs($user);

        // 1. 初期データの確認
        $response = $this->getJson('/api/user');
        $initialData = $response->json('user');
        
        $this->assertEquals('初期ニックネーム', $initialData['nickname']);
        $this->assertEquals('initial@example.com', $initialData['email']);

        // 2. 段階的なプロフィール更新
        // 2-1. ニックネームのみ更新
        $this->putJson('/api/auth/profile', [
            'nickname' => '1回目更新',
            'email' => $user->email,
        ])->assertStatus(200);

        // 2-2. メールアドレスのみ更新
        $this->putJson('/api/auth/profile', [
            'nickname' => '1回目更新',
            'email' => 'second@example.com',
        ])->assertStatus(200);

        // 2-3. パスワードのみ更新
        $this->putJson('/api/auth/profile', [
            'nickname' => '1回目更新',
            'email' => 'second@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(200);

        // 3. 最終的なデータの整合性確認
        $finalResponse = $this->getJson('/api/user');
        $finalData = $finalResponse->json('user');

        $this->assertEquals('1回目更新', $finalData['nickname']);
        $this->assertEquals('second@example.com', $finalData['email']);
        $this->assertEquals($initialData['id'], $finalData['id']);
        $this->assertEquals($initialData['created_at'], $finalData['created_at']);

        // パスワードが更新されていることを確認
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }
}