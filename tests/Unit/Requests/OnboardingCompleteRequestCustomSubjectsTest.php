<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\OnboardingCompleteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OnboardingCompleteRequestCustomSubjectsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * テストメソッド
     */
    #[Test]
    public function 有効なcustom_subjectsが受け入れられる()
    {
        $validData = [
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'ipa_fe',
                    'custom_subjects' => [
                        ['name' => 'データベース'],
                        ['name' => 'ネットワーク'],
                    ],
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
    public function custom_subjectsが10個を超える場合はバリデーションエラー()
    {
        $subjects = [];
        for ($i = 1; $i <= 11; $i++) {
            $subjects[] = ['name' => "学習分野{$i}"];
        }

        $invalidData = [
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'ipa_fe',
                    'custom_subjects' => $subjects,
                ],
            ],
        ];

        $request = new OnboardingCompleteRequest;
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('step_data.setup_step.custom_subjects', $validator->errors()->toArray());
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function custom_subjectsの名前が空の場合はバリデーションエラー()
    {
        $invalidData = [
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'ipa_fe',
                    'custom_subjects' => [
                        ['name' => ''],
                    ],
                ],
            ],
        ];

        $request = new OnboardingCompleteRequest;
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('step_data.setup_step.custom_subjects.0.name', $validator->errors()->toArray());
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function custom_subjectsの名前が255文字を超える場合はバリデーションエラー()
    {
        $invalidData = [
            'step_data' => [
                'setup_step' => [
                    'exam_type' => 'ipa_fe',
                    'custom_subjects' => [
                        ['name' => str_repeat('あ', 256)],
                    ],
                ],
            ],
        ];

        $request = new OnboardingCompleteRequest;
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('step_data.setup_step.custom_subjects.0.name', $validator->errors()->toArray());
    }
}
