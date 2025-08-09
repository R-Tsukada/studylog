<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserFutureVision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFutureVisionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // GET /api/user/future-vision のテスト

    public function test_can_get_future_vision_when_exists(): void
    {
        $vision = UserFutureVision::create([
            'user_id' => $this->user->id,
            'vision_text' => '資格を取得して、チームリーダーになる',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/user/future-vision');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $vision->id,
                    'user_id' => $this->user->id,
                    'vision_text' => '資格を取得して、チームリーダーになる',
                ],
            ]);
    }

    public function test_returns_204_when_no_future_vision_exists(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/user/future-vision');

        $response->assertStatus(204);
        // 204 No Content はレスポンスボディを持たない
    }

    public function test_cannot_get_future_vision_without_authentication(): void
    {
        $response = $this->getJson('/api/user/future-vision');

        $response->assertStatus(401);
    }

    // POST /api/user/future-vision のテスト

    public function test_can_create_future_vision(): void
    {
        $visionText = 'AWS認定を取得して、クラウドエキスパートになりたい';

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/user/future-vision', [
                'vision_text' => $visionText,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '将来のビジョンを保存しました',
                'data' => [
                    'user_id' => $this->user->id,
                    'vision_text' => $visionText,
                ],
            ]);

        $this->assertDatabaseHas('user_future_visions', [
            'user_id' => $this->user->id,
            'vision_text' => $visionText,
        ]);
    }

    public function test_cannot_create_future_vision_when_already_exists(): void
    {
        UserFutureVision::create([
            'user_id' => $this->user->id,
            'vision_text' => '既存のビジョン',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/user/future-vision', [
                'vision_text' => '新しいビジョンテキストを10文字以上で入力',
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => '将来のビジョンは既に登録されています。更新する場合はPUTメソッドを使用してください。',
            ]);
    }

    public function test_validates_vision_text_on_create(): void
    {
        // 必須チェック
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/user/future-vision', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vision_text']);

        // 最小文字数チェック
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/user/future-vision', [
                'vision_text' => '短い',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vision_text']);

        // 最大文字数チェック
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/user/future-vision', [
                'vision_text' => str_repeat('あ', 2001),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vision_text']);

        // HTML特殊文字チェック
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/user/future-vision', [
                'vision_text' => 'テスト<script>alert("xss")</script>',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vision_text']);
    }

    // PUT /api/user/future-vision のテスト

    public function test_can_update_future_vision(): void
    {
        $vision = UserFutureVision::create([
            'user_id' => $this->user->id,
            'vision_text' => '古いビジョン',
        ]);

        $newVisionText = '更新されたビジョンテキスト';

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/user/future-vision', [
                'vision_text' => $newVisionText,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '将来のビジョンを更新しました',
                'data' => [
                    'id' => $vision->id,
                    'user_id' => $this->user->id,
                    'vision_text' => $newVisionText,
                ],
            ]);

        $this->assertDatabaseHas('user_future_visions', [
            'id' => $vision->id,
            'vision_text' => $newVisionText,
        ]);
    }

    public function test_cannot_update_nonexistent_future_vision(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/user/future-vision', [
                'vision_text' => '更新テストで10文字以上にする',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => '更新対象の将来のビジョンが見つかりません。',
            ]);
    }

    // DELETE /api/user/future-vision のテスト

    public function test_can_delete_future_vision(): void
    {
        $vision = UserFutureVision::create([
            'user_id' => $this->user->id,
            'vision_text' => '削除対象のビジョン',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/user/future-vision');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '将来のビジョンを削除しました',
            ]);

        $this->assertDatabaseMissing('user_future_visions', [
            'id' => $vision->id,
        ]);
    }

    public function test_cannot_delete_nonexistent_future_vision(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/user/future-vision');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => '削除対象の将来のビジョンが見つかりません。',
            ]);
    }
}
