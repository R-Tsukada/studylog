<?php

namespace Tests\Feature;

use App\Models\ExamType;
use App\Models\SubjectArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OnboardingCustomSubjectsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'nickname' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'onboarding_completed_at' => null,
            'onboarding_skipped' => false,
        ]);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     */
    #[Test]
    public function 既定試験でカスタム学習分野が正しく作成される()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/onboarding/complete', [
                'completed_steps' => [1, 2, 3, 4],
                'total_time_spent' => 300,
                'step_data' => [
                    'setup_step' => [
                        'exam_type' => 'ipa_fe',
                        'exam_date' => '2025-08-15',
                        'daily_goal_minutes' => 60,
                        'custom_subjects' => [
                            ['name' => 'データベース'],
                            ['name' => 'ネットワーク基礎'],
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);

        // ExamTypeが作成されていることを確認
        $examType = ExamType::where('user_id', $this->user->id)
            ->where('code', 'ipa_fe')
            ->first();
        $this->assertNotNull($examType);
        $this->assertEquals('基本情報技術者試験', $examType->name);

        // SubjectAreaが作成されていることを確認
        $subjectAreas = SubjectArea::where('user_id', $this->user->id)
            ->where('exam_type_id', $examType->id)
            ->get();

        $this->assertCount(2, $subjectAreas);
        $this->assertEquals('データベース', $subjectAreas[0]->name);
        $this->assertEquals('ネットワーク基礎', $subjectAreas[1]->name);
        $this->assertFalse($subjectAreas[0]->is_system);
        $this->assertFalse($subjectAreas[1]->is_system);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     */
    #[Test]
    public function カスタム試験の学習分野作成機能が正常に動作する()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/onboarding/complete', [
                'completed_steps' => [1, 2, 3, 4],
                'total_time_spent' => 300,
                'step_data' => [
                    'setup_step' => [
                        'exam_type' => 'custom',
                        'custom_exam_name' => 'テスト資格',
                        'custom_exam_description' => 'テスト用の資格',
                        'custom_exam_color' => '#FF5722',
                        'custom_exam_subjects' => [
                            ['name' => '分野A'],
                            ['name' => '分野B'],
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);

        // カスタムExamTypeが作成されていることを確認
        $examType = ExamType::where('user_id', $this->user->id)
            ->where('name', 'テスト資格')
            ->first();
        $this->assertNotNull($examType);

        // SubjectAreaが作成されていることを確認
        $subjectAreas = SubjectArea::where('user_id', $this->user->id)
            ->where('exam_type_id', $examType->id)
            ->get();

        $this->assertCount(2, $subjectAreas);
        $this->assertEquals('分野A', $subjectAreas[0]->name);
        $this->assertEquals('分野B', $subjectAreas[1]->name);
    }

    
use PHPUnit\Framework\Attributes\Test;

/**
     * テストメソッド
     */
    #[Test]
    public function 空の学習分野名の場合はバリデーションエラー()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/onboarding/complete', [
                'completed_steps' => [1, 2, 3, 4],
                'total_time_spent' => 300,
                'step_data' => [
                    'setup_step' => [
                        'exam_type' => 'ipa_fe',
                        'custom_subjects' => [
                            ['name' => 'データベース'],
                            ['name' => ''],
                            ['name' => 'ネットワーク'],
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['step_data.setup_step.custom_subjects.1.name']);
    }
}
