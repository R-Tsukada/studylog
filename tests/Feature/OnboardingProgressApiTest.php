<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingProgressApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * @test
     */
    public function 正しいパラメータで進捗更新が成功する()
    {
        $requestData = [
            'current_step' => 2,
            'completed_steps' => [1],
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'aws_clf',
                    'exam_date' => '2025-08-15',
                    'daily_goal_minutes' => 60,
                ]
            ],
            'timestamp' => '2025-07-27T14:34:37Z' // ミリ秒なし
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', $requestData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'current_step' => 2,
                    'completed_steps' => [1],
                ]
            ]);
    }

    /**
     * @test
     */
    public function current_stepが欠如している場合に422エラーになる()
    {
        $requestData = [
            // current_step が欠如
            'completed_steps' => [1],
            'step_data' => ['test' => 'data'],
            'timestamp' => '2025-07-27T14:34:37Z'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_step']);
            
        // エラーメッセージも確認
        $errors = $response->json('errors');
        $this->assertStringContainsString('現在のステップは必須です', $errors['current_step'][0]);
    }

    /**
     * @test
     */
    public function ミリ秒付きタイムスタンプで422エラーになる()
    {
        $requestData = [
            'current_step' => 2,
            'completed_steps' => [1],
            'step_data' => ['test' => 'data'],
            'timestamp' => '2025-07-27T14:34:37.123Z' // ミリ秒付き（JavaScriptデフォルト）
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['timestamp']);
    }

    /**
     * @test
     */
    public function JavaScriptのtoISOString修正版が動作する()
    {
        // JavaScriptで new Date().toISOString().replace(/\.\d{3}Z$/, 'Z') した形式
        $requestData = [
            'current_step' => 3,
            'completed_steps' => [1, 2],
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'custom',
                    'custom_exam_name' => 'テスト試験',
                    'daily_goal_minutes' => 120,
                ]
            ],
            'timestamp' => '2025-07-27T14:34:37Z' // ミリ秒除去済み
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', $requestData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        // データベースに保存されているか確認
        $this->user->refresh();
        $progress = $this->user->onboarding_progress;
        
        $this->assertEquals(3, $progress['current_step']);
        $this->assertEquals([1, 2], $progress['completed_steps']);
        $this->assertArrayHasKey('setup_step', $progress['step_data']);
    }

    /**
     * @test
     */
    public function camelCase形式のパラメータで422エラーになる()
    {
        // 修正前の誤ったパラメータ名（camelCase）
        $requestData = [
            'currentStep' => 2,  // 間違った形式
            'completedSteps' => [1],  // 間違った形式
            'stepData' => ['test' => 'data'],  // 間違った形式
            'timestamp' => '2025-07-27T14:34:37Z'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', $requestData);

        $response->assertStatus(422);
        
        // current_step が必須エラーになることを確認
        $this->assertArrayHasKey('current_step', $response->json('errors'));
    }

    /**
     * @test
     */
    public function 認証なしで401エラーになる()
    {
        $requestData = [
            'current_step' => 2,
            'completed_steps' => [1],
            'step_data' => ['test' => 'data'],
            'timestamp' => '2025-07-27T14:34:37Z'
        ];

        $response = $this->postJson('/api/onboarding/progress', $requestData);

        $response->assertStatus(401);
    }

    /**
     * @test
     */
    public function 複雑なstep_dataが正常に処理される()
    {
        $requestData = [
            'current_step' => 2,
            'completed_steps' => [1],
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'custom',
                    'exam_date' => '2025-09-01',
                    'daily_goal_minutes' => 90,
                    'custom_exam_name' => '情報セキュリティマネジメント試験',
                    'custom_exam_description' => 'セキュリティ関連の資格試験で、情報システムの企画・要件定義・開発・運用・保守における情報セキュリティ管理の推進又は支援を行う者を対象とした試験です。',
                    'custom_exam_color' => '#FF5722',
                    'custom_exam_notes' => 'スコア目標: 700点以上、受験料: 7,500円、午前・午後各90分'
                ],
                'welcome_step' => [
                    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                    'screen_resolution' => '1920x1080',
                    'timezone' => 'Asia/Tokyo'
                ]
            ],
            'timestamp' => '2025-07-27T14:34:37Z'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', $requestData);

        $response->assertStatus(200);

        // 複雑なデータが正しく保存されているか確認
        $this->user->refresh();
        $progress = $this->user->onboarding_progress;
        
        $this->assertEquals('情報セキュリティマネジメント試験', 
            $progress['step_data']['setup_step']['custom_exam_name']);
        $this->assertEquals('#FF5722', 
            $progress['step_data']['setup_step']['custom_exam_color']);
        $this->assertArrayHasKey('welcome_step', $progress['step_data']);
    }
}