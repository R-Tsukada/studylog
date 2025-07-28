<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\OnboardingController;
use App\Models\ExamType;
use App\Models\SubjectArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class OnboardingControllerCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    private OnboardingController $controller;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->controller = new OnboardingController(
            app(\App\Services\OnboardingService::class)
        );
        
        $this->user = User::factory()->create();
    }

    /**
     * @test
     */
    public function 試験コード生成_通常の名前()
    {
        $examCode = $this->invokePrivateMethod('generateExamCode', [1, 'AWS Solutions Architect']);
        
        $this->assertStringContainsString('awssolutio', $examCode); // 10文字制限で切り詰められる
        $this->assertStringContainsString('_u1_', $examCode);
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $examCode);
    }

    /**
     * @test
     */
    public function 試験コード生成_空の名前の場合のフォールバック()
    {
        $examCode = $this->invokePrivateMethod('generateExamCode', [1, '']);
        
        $this->assertStringContainsString('custom', $examCode);
        $this->assertStringContainsString('_u1_', $examCode);
    }

    /**
     * @test
     */
    public function 試験コード生成_特殊文字を含む名前()
    {
        $examCode = $this->invokePrivateMethod('generateExamCode', [1, 'テスト@試験#2024']);
        
        $this->assertStringContainsString('2024', $examCode);
        $this->assertStringContainsString('_u1_', $examCode);
        $this->assertDoesNotMatchRegularExpression('/[@#]/', $examCode);
    }

    /**
     * @test
     */
    public function 試験コード生成_重複チェック機能()
    {
        // 既存のコードを作成
        ExamType::factory()->create([
            'code' => 'testexam_u1_' . time() . '_1000',
            'user_id' => $this->user->id,
        ]);

        $examCode = $this->invokePrivateMethod('generateExamCode', [1, 'Test Exam']);
        
        $this->assertNotEquals('testexam_u1_' . time() . '_1000', $examCode);
        $this->assertStringContainsString('testexam', $examCode);
    }

    /**
     * @test
     */
    public function 学習分野コード生成_通常の名前()
    {
        $subjectCode = $this->invokePrivateMethod('generateSubjectCode', ['データ構造とアルゴリズム', 1]);
        
        $this->assertStringContainsString('_1_', $subjectCode);
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $subjectCode);
    }

    /**
     * @test
     */
    public function 学習分野コード生成_空の名前の場合のフォールバック()
    {
        $subjectCode = $this->invokePrivateMethod('generateSubjectCode', ['', 1]);
        
        $this->assertStringContainsString('subject', $subjectCode);
        $this->assertStringContainsString('_1_', $subjectCode);
    }

    /**
     * @test
     */
    public function 学習分野コード生成_重複チェック機能()
    {
        // 既存のコードを作成
        SubjectArea::factory()->create([
            'code' => 'testsubject_1_' . time(),
            'user_id' => $this->user->id,
        ]);

        $subjectCode = $this->invokePrivateMethod('generateSubjectCode', ['Test Subject', 1]);
        
        $this->assertNotEquals('testsubject_1_' . time(), $subjectCode);
        $this->assertStringContainsString('testsubjec', $subjectCode); // 10文字制限で切り詰められる
    }

    /**
     * プライベートメソッドを呼び出すためのヘルパー
     */
    private function invokePrivateMethod(string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($this->controller, $parameters);
    }
}