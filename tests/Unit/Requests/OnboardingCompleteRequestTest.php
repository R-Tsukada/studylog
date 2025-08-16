<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\OnboardingCompleteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OnboardingCompleteRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * テストメソッド
     */
    #[Test]
    public function 基本的なバリデーションルールが正しく設定されている()
    {
        $request = new OnboardingCompleteRequest;
        $rules = $request->rules();

        // 基本フィールドの確認
        $this->assertArrayHasKey('completed_steps', $rules);
        $this->assertArrayHasKey('total_time_spent', $rules);
        $this->assertArrayHasKey('step_times', $rules);
        $this->assertArrayHasKey('feedback', $rules);

        // step_data の確認
        $this->assertArrayHasKey('step_data', $rules);
        $this->assertArrayHasKey('step_data.setup_step', $rules);
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function step_data_setup_stepのバリデーションルールが正しく設定されている()
    {
        $request = new OnboardingCompleteRequest;
        $rules = $request->rules();

        // setup_step の詳細フィールド
        $this->assertArrayHasKey('step_data.setup_step.exam_type', $rules);
        $this->assertArrayHasKey('step_data.setup_step.exam_date', $rules);
        $this->assertArrayHasKey('step_data.setup_step.daily_goal_minutes', $rules);

        // カスタム試験フィールド
        $this->assertArrayHasKey('step_data.setup_step.custom_exam_name', $rules);
        $this->assertArrayHasKey('step_data.setup_step.custom_exam_description', $rules);
        $this->assertArrayHasKey('step_data.setup_step.custom_exam_color', $rules);
        $this->assertArrayHasKey('step_data.setup_step.custom_exam_notes', $rules);
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function 有効なstep_dataが受け入れられる()
    {
        $validData = [
            'completed_steps' => [1, 2, 3, 4],
            'total_time_spent' => 300,
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'aws_clf',
                    'exam_date' => now()->addDays(30)->format('Y-m-d'), // 30日後の日付
                    'daily_goal_minutes' => 60,
                ],
            ],
        ];

        $request = new OnboardingCompleteRequest;
        $validator = Validator::make($validData, $request->rules());

        $this->assertTrue($validator->passes());
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function カスタム試験データが有効な場合に受け入れられる()
    {
        $validData = [
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

        $request = new OnboardingCompleteRequest;
        $validator = Validator::make($validData, $request->rules());

        $this->assertTrue($validator->passes());
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function 試験日が過去の場合にバリデーションエラーになる()
    {
        $invalidData = [
            'step_data' => [
                'setup_step' => [
                    'exam_date' => '2020-01-01', // 過去の日付
                ],
            ],
        ];

        $request = new OnboardingCompleteRequest;
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('step_data.setup_step.exam_date', $validator->errors()->toArray());
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function 学習時間が無効な値の場合にバリデーションエラーになる()
    {
        $invalidData = [
            'step_data' => [
                'setup_step' => [
                    'daily_goal_minutes' => 1500, // 24時間超過
                ],
            ],
        ];

        $request = new OnboardingCompleteRequest;
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('step_data.setup_step.daily_goal_minutes', $validator->errors()->toArray());
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function カスタム試験名が長すぎる場合にバリデーションエラーになる()
    {
        $invalidData = [
            'step_data' => [
                'setup_step' => [
                    'custom_exam_name' => str_repeat('a', 256), // 255文字超過
                ],
            ],
        ];

        $request = new OnboardingCompleteRequest;
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('step_data.setup_step.custom_exam_name', $validator->errors()->toArray());
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function カスタム試験カラーが無効な形式の場合にバリデーションエラーになる()
    {
        $invalidData = [
            'step_data' => [
                'setup_step' => [
                    'custom_exam_color' => 'invalid-color', // 無効なカラーコード
                ],
            ],
        ];

        $request = new OnboardingCompleteRequest;
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('step_data.setup_step.custom_exam_color', $validator->errors()->toArray());
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function step_dataが空でも受け入れられる()
    {
        $validData = [
            'completed_steps' => [1, 2, 3, 4],
            'total_time_spent' => 300,
        ];

        $request = new OnboardingCompleteRequest;
        $validator = Validator::make($validData, $request->rules());

        $this->assertTrue($validator->passes());
    }
}
