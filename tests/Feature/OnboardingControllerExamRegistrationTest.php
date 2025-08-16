<?php

namespace Tests\Feature;

use App\Models\ExamType;
use App\Models\StudyGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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
     * テストメソッド
     */
    #[Test]
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
                    'custom_exam_notes' => 'スコア目標: 700点以上',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $requestData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'setup_complete' => true,
                ],
            ]);

        // ExamTypeが作成されているか確認
        $this->assertDatabaseHas('exam_types', [
            'user_id' => $this->user->id,
            'name' => '情報セキュリティマネジメント試験',
            'description' => 'セキュリティ関連の資格試験',
            'color' => '#FF5722',
            'exam_notes' => 'スコア目標: 700点以上',
            'is_system' => 0,
            'is_active' => 1,
        ]);

        $examType = ExamType::where('user_id', $this->user->id)->first();
        $this->assertEquals('2025-09-01', $examType->exam_date->format('Y-m-d'));

        // StudyGoalが作成されているか確認
        $this->assertDatabaseHas('study_goals', [
            'user_id' => $this->user->id,
            'exam_type_id' => $examType->id,
            'daily_minutes_goal' => 90,
            'is_active' => 1,
        ]);

        $studyGoal = StudyGoal::where('user_id', $this->user->id)->first();
        $this->assertEquals('2025-09-01', $studyGoal->exam_date->format('Y-m-d'));

        // オンボーディング完了の確認
        $this->user->refresh();
        $this->assertNotNull($this->user->onboarding_completed_at);
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function complete_で既存試験タイプと学習目標が作成される()
    {
        $requestData = [
            'completed_steps' => [1, 2, 3, 4],
            'total_time_spent' => 180,
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'aws_clf',
                    'exam_date' => now()->addDays(30)->format('Y-m-d'),
                    'daily_goal_minutes' => 60,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $requestData);

        $response->assertStatus(200);

        // 既存試験タイプのユーザー固有インスタンスが作成される
        $this->assertDatabaseHas('exam_types', [
            'user_id' => $this->user->id,
            'code' => 'aws_clf',
            'name' => 'AWS Cloud Practitioner',
            'is_system' => 0,
            'is_active' => 1,
        ]);

        $examType = ExamType::where('user_id', $this->user->id)->first();
        $expectedDate = now()->addDays(30)->format('Y-m-d');
        $this->assertEquals($expectedDate, $examType->exam_date->format('Y-m-d'));

        // 学習目標が作成される
        $this->assertDatabaseHas('study_goals', [
            'user_id' => $this->user->id,
            'exam_type_id' => $examType->id,
            'daily_minutes_goal' => 60,
            'is_active' => 1,
        ]);

        $studyGoal = StudyGoal::where('user_id', $this->user->id)->first();
        $this->assertEquals($expectedDate, $studyGoal->exam_date->format('Y-m-d'));
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function complete_で既存アクティブ学習目標が無効化される()
    {
        // 既存のアクティブ学習目標を作成
        $existingGoal = StudyGoal::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $requestData = [
            'completed_steps' => [1, 2, 3, 4],
            'total_time_spent' => 120,
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'jstqb_fl',
                    'daily_goal_minutes' => 45,
                ],
            ],
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
     * テストメソッド
     */
    #[Test]
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
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseMissing('study_goals', [
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function complete_でトランザクションエラー時にロールバックされる()
    {
        // SQLiteのNOT NULL制約エラーを意図的に発生させる
        // nameフィールドにnullを設定してエラーを起こす
        $requestData = [
            'completed_steps' => [1, 2, 3, 4],
            'total_time_spent' => 300,
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'custom',
                    'custom_exam_name' => null, // nullでエラーを発生
                    'daily_goal_minutes' => 60,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $requestData);

        // バリデーションエラーで422が返る場合もある
        $this->assertTrue(in_array($response->getStatusCode(), [422, 500]));

        // ロールバックの確認（どちらの場合でも変更は反映されない）
        $this->user->refresh();

        $this->assertDatabaseMissing('exam_types', [
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseMissing('study_goals', [
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * テストメソッド
     */
    #[Test]
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
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $requestData);

        $response->assertStatus(200);

        // 生成されたコードの確認
        $examType = ExamType::where('user_id', $this->user->id)->first();
        $this->assertNotNull($examType);
        $this->assertStringContainsString((string) $this->user->id, $examType->code);
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $examType->code);
    }
}
