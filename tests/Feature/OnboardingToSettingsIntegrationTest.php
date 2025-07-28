<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ExamType;
use App\Models\StudyGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
     * @test
     */
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
                    'custom_exam_notes' => 'スコア目標: 700点以上'
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $onboardingData);

        // オンボーディング完了の確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'setup_complete' => true
                ]
            ]);

        // 2. データベースにExamTypeが作成されているか確認
        $this->assertDatabaseHas('exam_types', [
            'user_id' => $this->user->id,
            'name' => '情報セキュリティマネジメント試験',
            'description' => 'セキュリティ関連の資格試験',
            'color' => '#FF5722',
            'exam_notes' => 'スコア目標: 700点以上',
            'is_system' => 0,
            'is_active' => 1
        ]);

        $examType = ExamType::where('user_id', $this->user->id)->first();
        $this->assertNotNull($examType);
        $this->assertEquals('2025-09-01', $examType->exam_date->format('Y-m-d'));

        // 3. StudyGoalが作成されているか確認
        $this->assertDatabaseHas('study_goals', [
            'user_id' => $this->user->id,
            'exam_type_id' => $examType->id,
            'daily_minutes_goal' => 90,
            'is_active' => 1
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
                        'is_active' => true
                    ]
                ]
            ]);

        // 5. オンボーディング完了状態の確認
        $this->user->refresh();
        $this->assertNotNull($this->user->onboarding_completed_at);
    }

    /**
     * @test  
     */
    public function オンボーディング完了後に設定画面で既存試験が表示される()
    {
        // 既存試験タイプでのオンボーディング完了
        $onboardingData = [
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
            ->postJson('/api/onboarding/complete', $onboardingData);

        $response->assertStatus(200);

        // 既存試験タイプのユーザー固有インスタンスが作成される
        $this->assertDatabaseHas('exam_types', [
            'user_id' => $this->user->id,
            'code' => 'aws_clf',
            'name' => 'AWS Cloud Practitioner',
            'is_system' => 0,
            'is_active' => 1
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
     * @test
     */
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
            'user_id' => $this->user->id
        ]);

        // 設定画面では空の配列が返る
        $settingsResponse = $this->actingAs($this->user)
            ->getJson('/api/user/exam-types');

        $settingsResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'exam_types' => []
            ]);
    }

    /**
     * @test
     */
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
                    'custom_exam_notes' => 'スコア目標: 700点以上'
                ]
            ]
        ];

        echo "\n=== オンボーディングAPIテスト開始 ===\n";
        echo "Request Data: " . json_encode($onboardingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $onboardingData);

        echo "Response Status: " . $response->getStatusCode() . "\n";
        echo "Response Body: " . $response->getContent() . "\n";

        if ($response->getStatusCode() !== 200) {
            echo "=== エラー詳細 ===\n";
            $this->fail('オンボーディング完了APIが失敗しました');
        }

        // データベース確認
        $examTypes = ExamType::where('user_id', $this->user->id)->get();
        echo "作成されたExamType数: " . $examTypes->count() . "\n";
        
        if ($examTypes->count() > 0) {
            echo "ExamType詳細: " . json_encode($examTypes->first()->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }

        $studyGoals = StudyGoal::where('user_id', $this->user->id)->get();
        echo "作成されたStudyGoal数: " . $studyGoals->count() . "\n";

        if ($studyGoals->count() > 0) {
            echo "StudyGoal詳細: " . json_encode($studyGoals->first()->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }

        // 設定画面API確認
        echo "\n=== 設定画面APIテスト ===\n";
        $settingsResponse = $this->actingAs($this->user)
            ->getJson('/api/user/exam-types');

        echo "Settings API Status: " . $settingsResponse->getStatusCode() . "\n";
        echo "Settings API Response: " . $settingsResponse->getContent() . "\n";

        echo "=== テスト完了 ===\n";

        $this->assertTrue(true); // テスト成功
    }
}