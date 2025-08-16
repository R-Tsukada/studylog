<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\OnboardingProgressRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OnboardingProgressRequestTest extends TestCase
{
    use RefreshDatabase;

    

/**
     * テストメソッド
     */
    #[Test]
    public function current_stepが必須であること()
    {
        $invalidData = [
            'completed_steps' => [1, 2],
            'step_data' => ['test' => 'data'],
            'timestamp' => '2025-07-27T14:34:37Z',
        ];

        $request = new OnboardingProgressRequest;
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('current_step', $validator->errors()->toArray());
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function current_stepが数値でない場合にバリデーションエラーになる()
    {
        $invalidData = [
            'current_step' => 'invalid',
            'completed_steps' => [1, 2],
            'step_data' => ['test' => 'data'],
            'timestamp' => '2025-07-27T14:34:37Z',
        ];

        $request = new OnboardingProgressRequest;
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('current_step', $validator->errors()->toArray());
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function current_stepが範囲外の場合にバリデーションエラーになる()
    {
        $invalidData = [
            'current_step' => 0, // 1未満
            'completed_steps' => [1, 2],
            'step_data' => ['test' => 'data'],
            'timestamp' => '2025-07-27T14:34:37Z',
        ];

        $request = new OnboardingProgressRequest;
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('current_step', $validator->errors()->toArray());

        // 最大値超過もテスト
        $invalidData['current_step'] = 5; // 4（デフォルト）を超過
        $validator = Validator::make($invalidData, $request->rules());
        $this->assertTrue($validator->fails());
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function timestampが正しいフォーマットでない場合にバリデーションエラーになる()
    {
        // ミリ秒付きのタイムスタンプ（JavaScriptのtoISOString()デフォルト）
        $invalidData = [
            'current_step' => 2,
            'completed_steps' => [1],
            'step_data' => ['test' => 'data'],
            'timestamp' => '2025-07-27T14:34:37.123Z', // ミリ秒付き
        ];

        $request = new OnboardingProgressRequest;
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('timestamp', $validator->errors()->toArray());
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function timestampが正しいフォーマットの場合に受け入れられる()
    {
        $validData = [
            'current_step' => 2,
            'completed_steps' => [1],
            'step_data' => ['test' => 'data'],
            'timestamp' => '2025-07-27T14:34:37Z', // ミリ秒なし
        ];

        $request = new OnboardingProgressRequest;
        $validator = Validator::make($validData, $request->rules());

        $this->assertTrue($validator->passes());
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function timestampがnullの場合も受け入れられる()
    {
        $validData = [
            'current_step' => 2,
            'completed_steps' => [1],
            'step_data' => ['test' => 'data'],
            'timestamp' => null,
        ];

        $request = new OnboardingProgressRequest;
        $validator = Validator::make($validData, $request->rules());

        $this->assertTrue($validator->passes());
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function 有効なデータが受け入れられる()
    {
        $validData = [
            'current_step' => 2,
            'completed_steps' => [1],
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'aws_clf',
                    'exam_date' => '2025-08-15',
                    'daily_goal_minutes' => 60,
                ],
            ],
            'timestamp' => '2025-07-27T14:34:37Z',
        ];

        $request = new OnboardingProgressRequest;
        $validator = Validator::make($validData, $request->rules());

        $this->assertTrue($validator->passes());
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function completed_stepsが無効な値の場合にバリデーションエラーになる()
    {
        $invalidData = [
            'current_step' => 2,
            'completed_steps' => [0, 5], // 範囲外の値
            'step_data' => ['test' => 'data'],
            'timestamp' => '2025-07-27T14:34:37Z',
        ];

        $request = new OnboardingProgressRequest;
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('completed_steps.0', $validator->errors()->toArray());
        $this->assertArrayHasKey('completed_steps.1', $validator->errors()->toArray());
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function step_dataが大きすぎる場合にバリデーションエラーになる()
    {
        // 大きなデータを作成（10KB超過）
        $largeData = str_repeat('x', 11000);

        $invalidData = [
            'current_step' => 2,
            'completed_steps' => [1],
            'step_data' => [
                'large_field' => $largeData,
            ],
            'timestamp' => '2025-07-27T14:34:37Z',
        ];

        $request = new OnboardingProgressRequest;
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('step_data', $validator->errors()->toArray());
    }

    

/**
     * テストメソッド
     */
    #[Test]
    public function java_scriptからの_ap_iコール形式をテスト()
    {
        // 実際のJavaScriptから送信される可能性のあるデータ形式
        $jsStyleData = [
            'current_step' => 2,
            'completed_steps' => [1],
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
            'timestamp' => '2025-07-27T14:34:37Z', // ミリ秒除去済み
        ];

        $request = new OnboardingProgressRequest;
        $validator = Validator::make($jsStyleData, $request->rules());

        $this->assertTrue($validator->passes());
    }
}
