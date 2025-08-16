<?php

namespace Tests\Feature;

use App\Models\ExamType;
use App\Models\StudyGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OnboardingToSettingsIntegrationTest extends TestCase
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
    public function オンボーディング完了後に設定画面でカスタム試験が表示される()
    {
        // 1. オンボーディング完了API呼び出し
        $onboardingData = [
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
            ->postJson('/api/onboarding/complete', $onboardingData);

        // オンボーディング完了の確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'setup_complete' => true,
                ],
            ]);

        // 2. データベースにExamTypeが作成されているか確認
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
        $this->assertNotNull($examType);
        $this->assertEquals('2025-09-01', $examType->exam_date->format('Y-m-d'));

        // 3. StudyGoalが作成されているか確認
        $this->assertDatabaseHas('study_goals', [
            'user_id' => $this->user->id,
            'exam_type_id' => $examType->id,
            'daily_minutes_goal' => 90,
            'is_active' => 1,
        ]);

        $studyGoal = StudyGoal::where('user_id', $this->user->id)->first();
        $this->assertEquals('2025-09-01', $studyGoal->exam_date->format('Y-m-d'));

        // 4. 設定画面のAPIで正しく取得できるか確認
        $settingsResponse = $this->actingAs($this->user)
            ->getJson('/api/user/exam-types');

        $settingsResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'exam_types' => [
                    [
                        'id' => $examType->id,
                        'name' => '情報セキュリティマネジメント試験',
                        'description' => 'セキュリティ関連の資格試験',
                        'color' => '#FF5722',
                        'exam_notes' => 'スコア目標: 700点以上',
                        'is_system' => false,
                        'is_active' => true,
                    ],
                ],
            ]);

        // 5. オンボーディング完了状態の確認
        $this->user->refresh();
        $this->assertNotNull($this->user->onboarding_completed_at);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function オンボーディング完了後に設定画面で既存試験が表示される()
    {
        // 既存試験タイプでのオンボーディング完了
        $onboardingData = [
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
            ->postJson('/api/onboarding/complete', $onboardingData);

        $response->assertStatus(200);

        // 既存試験タイプのユーザー固有インスタンスが作成される
        $this->assertDatabaseHas('exam_types', [
            'user_id' => $this->user->id,
            'code' => 'aws_clf',
            'name' => 'AWS Cloud Practitioner',
            'is_system' => 0,
            'is_active' => 1,
        ]);

        // 設定画面のAPIで取得確認
        $settingsResponse = $this->actingAs($this->user)
            ->getJson('/api/user/exam-types');

        $settingsResponse->assertStatus(200);

        $examTypes = $settingsResponse->json('exam_types');
        $this->assertCount(1, $examTypes);
        $this->assertEquals('AWS Cloud Practitioner', $examTypes[0]['name']);
        $this->assertEquals('aws_clf', $examTypes[0]['code']);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function step_dataが空の場合でもエラーにならない()
    {
        $onboardingData = [
            'completed_steps' => [1, 2, 3, 4],
            'total_time_spent' => 60,
            // step_data なし
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $onboardingData);

        $response->assertStatus(200);

        // オンボーディングは完了する
        $this->user->refresh();
        $this->assertNotNull($this->user->onboarding_completed_at);

        // ExamTypeやStudyGoalは作成されない
        $this->assertDatabaseMissing('exam_types', [
            'user_id' => $this->user->id,
        ]);

        // 設定画面では空の配列が返る
        $settingsResponse = $this->actingAs($this->user)
            ->getJson('/api/user/exam-types');

        $settingsResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'exam_types' => [],
            ]);
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function デバッグ用_詳細ログ出力()
    {
        // テスト実行時に詳細な情報を出力
        $onboardingData = [
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

        // オンボーディング完了API実行
        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $onboardingData);

        $response->assertStatus(200);

        // データベース確認
        $examTypes = ExamType::where('user_id', $this->user->id)->get();
        $this->assertGreaterThan(0, $examTypes->count(), 'ExamTypeが作成されていません');

        $studyGoals = StudyGoal::where('user_id', $this->user->id)->get();
        $this->assertGreaterThan(0, $studyGoals->count(), 'StudyGoalが作成されていません');

        // 設定画面API確認
        $settingsResponse = $this->actingAs($this->user)
            ->getJson('/api/user/exam-types');

        $settingsResponse->assertStatus(200);
        $settingsData = $settingsResponse->json();

        $this->assertTrue($settingsData['success']);
        $this->assertNotEmpty($settingsData['exam_types']);

        // 作成されたカスタム試験が設定画面に表示されることを確認
        $examNames = collect($settingsData['exam_types'])->pluck('name')->toArray();
        $this->assertContains('情報セキュリティマネジメント試験', $examNames);
    }
}
