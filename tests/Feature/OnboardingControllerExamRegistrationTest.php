<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ExamType;
use App\Models\StudyGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingControllerExamRegistrationTest extends TestCase
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
    public function complete_でカスタム試験と学習目標が作成される()
    {
        $requestData = [
            'completed_steps' => [1, 2, 3, 4],
            'total_time_spent' => 300,
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'custom',
                    'exam_date' => '2025-09-01',
                    'daily_goal_minutes' => 90,
                    'custom_exam_name' => '情報セキュリティマネジメント試験',
                    'custom_exam_description' => 'セキュリティ関連の資格試験',
                    'custom_exam_color' => '#FF5722',
                    'custom_exam_notes' => 'スコア目標: 700点以上'
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $requestData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'setup_complete' => true
                ]
            ]);

        // ExamTypeが作成されているか確認
        $this->assertDatabaseHas('exam_types', [
            'user_id' => $this->user->id,
            'name' => '情報セキュリティマネジメント試験',
            'description' => 'セキュリティ関連の資格試験',
            'exam_date' => '2025-09-01',
            'color' => '#FF5722',
            'exam_notes' => 'スコア目標: 700点以上',
            'is_system' => false,
            'is_active' => true
        ]);

        // StudyGoalが作成されているか確認
        $examType = ExamType::where('user_id', $this->user->id)->first();
        $this->assertDatabaseHas('study_goals', [
            'user_id' => $this->user->id,
            'exam_type_id' => $examType->id,
            'daily_minutes_goal' => 90,
            'exam_date' => '2025-09-01',
            'is_active' => true
        ]);

        // オンボーディング完了の確認
        $this->user->refresh();
        $this->assertNotNull($this->user->onboarding_completed_at);
    }

    /**
     * @test
     */
    public function complete_で既存試験タイプと学習目標が作成される()
    {
        $requestData = [
            'completed_steps' => [1, 2, 3, 4],
            'total_time_spent' => 180,
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'aws_clf',
                    'exam_date' => '2025-08-15',
                    'daily_goal_minutes' => 60,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $requestData);

        $response->assertStatus(200);

        // 既存試験タイプのユーザー固有インスタンスが作成される
        $this->assertDatabaseHas('exam_types', [
            'user_id' => $this->user->id,
            'code' => 'aws_clf',
            'name' => 'AWS Cloud Practitioner',
            'exam_date' => '2025-08-15',
            'is_system' => false,
            'is_active' => true
        ]);

        // 学習目標が作成される
        $examType = ExamType::where('user_id', $this->user->id)->first();
        $this->assertDatabaseHas('study_goals', [
            'user_id' => $this->user->id,
            'exam_type_id' => $examType->id,
            'daily_minutes_goal' => 60,
            'exam_date' => '2025-08-15',
            'is_active' => true
        ]);
    }

    /**
     * @test
     */
    public function complete_で既存アクティブ学習目標が無効化される()
    {
        // 既存のアクティブ学習目標を作成
        $existingGoal = StudyGoal::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true
        ]);

        $requestData = [
            'completed_steps' => [1, 2, 3, 4],
            'total_time_spent' => 120,
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'jstqb_fl',
                    'daily_goal_minutes' => 45,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $requestData);

        $response->assertStatus(200);

        // 既存目標が無効化される
        $existingGoal->refresh();
        $this->assertFalse($existingGoal->is_active);

        // 新しい目標がアクティブになる
        $newGoal = StudyGoal::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->first();
        $this->assertNotNull($newGoal);
        $this->assertEquals(45, $newGoal->daily_minutes_goal);
    }

    /**
     * @test
     */
    public function complete_でstep_dataが空の場合も正常処理される()
    {
        $requestData = [
            'completed_steps' => [1, 2, 3, 4],
            'total_time_spent' => 60,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $requestData);

        $response->assertStatus(200);

        // オンボーディングは完了する
        $this->user->refresh();
        $this->assertNotNull($this->user->onboarding_completed_at);

        // ExamTypeやStudyGoalは作成されない
        $this->assertDatabaseMissing('exam_types', [
            'user_id' => $this->user->id
        ]);
        $this->assertDatabaseMissing('study_goals', [
            'user_id' => $this->user->id
        ]);
    }

    /**
     * @test
     */
    public function complete_でトランザクションエラー時にロールバックされる()
    {
        // 無効なデータでExamType作成を失敗させる
        $requestData = [
            'completed_steps' => [1, 2, 3, 4],
            'total_time_spent' => 300,
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'custom',
                    'custom_exam_name' => str_repeat('x', 300), // 文字数制限超過
                    'daily_goal_minutes' => 60,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $requestData);

        $response->assertStatus(500);

        // ロールバックの確認
        $this->user->refresh();
        $this->assertNull($this->user->onboarding_completed_at);
        
        $this->assertDatabaseMissing('exam_types', [
            'user_id' => $this->user->id
        ]);
        $this->assertDatabaseMissing('study_goals', [
            'user_id' => $this->user->id
        ]);
    }

    /**
     * @test
     */
    public function complete_でカスタム試験コード生成が正しく動作する()
    {
        $requestData = [
            'completed_steps' => [1, 2, 3, 4],
            'total_time_spent' => 200,
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'custom',
                    'custom_exam_name' => '基本情報技術者試験 特別版',
                    'daily_goal_minutes' => 120,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $requestData);

        $response->assertStatus(200);

        // 生成されたコードの確認
        $examType = ExamType::where('user_id', $this->user->id)->first();
        $this->assertNotNull($examType);
        $this->assertStringContainsString((string)$this->user->id, $examType->code);
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $examType->code);
    }
}