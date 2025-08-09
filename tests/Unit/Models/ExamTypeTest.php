<?php

namespace Tests\Unit\Models;

use App\Models\ExamType;
use App\Models\StudyGoal;
use App\Models\SubjectArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->seed();
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function exam_type_has_correct_fillable_attributes()
    {
        $examType = new ExamType;
        $expected = [
            'code', 'name', 'description', 'is_active',
            'user_id', 'is_system', 'exam_date', 'exam_notes', 'color',
        ];

        $this->assertEquals($expected, $examType->getFillable());
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function exam_type_casts_is_active_to_boolean()
    {
        $examType = ExamType::factory()->create(['is_active' => 1]);

        $this->assertIsBool($examType->is_active);
        $this->assertTrue($examType->is_active);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function exam_type_casts_is_system_to_boolean()
    {
        $examType = ExamType::factory()->create(['is_system' => 1]);

        $this->assertIsBool($examType->is_system);
        $this->assertTrue($examType->is_system);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function exam_type_casts_exam_date_to_date()
    {
        $examType = ExamType::factory()->create(['exam_date' => '2025-06-15']);

        $this->assertInstanceOf(\Carbon\Carbon::class, $examType->exam_date);
        $this->assertEquals('2025-06-15', $examType->exam_date->format('Y-m-d'));
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function exam_type_has_many_subject_areas()
    {
        $examType = ExamType::factory()->create();
        $subjectArea = SubjectArea::factory()->create(['exam_type_id' => $examType->id]);

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $examType->subjectAreas);
        $this->assertInstanceOf(SubjectArea::class, $examType->subjectAreas->first());
        $this->assertEquals($subjectArea->id, $examType->subjectAreas->first()->id);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function exam_type_has_many_study_goals()
    {
        $examType = ExamType::factory()->create();
        $studyGoal = StudyGoal::factory()->create(['exam_type_id' => $examType->id]);

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $examType->studyGoals);
        $this->assertInstanceOf(StudyGoal::class, $examType->studyGoals->first());
        $this->assertEquals($studyGoal->id, $examType->studyGoals->first()->id);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function active_scope_returns_only_active_exam_types()
    {
        // 既存のアクティブなExamTypeの数を取得
        $existingActiveCount = ExamType::active()->count();

        // 新しくテスト用のExamTypeを作成
        ExamType::factory()->create(['is_active' => true, 'code' => 'test_active_1']);
        ExamType::factory()->create(['is_active' => true, 'code' => 'test_active_2']);
        ExamType::factory()->create(['is_active' => false, 'code' => 'test_inactive_1']);

        $activeExamTypes = ExamType::active()->get();
        $inactiveExamTypes = ExamType::where('is_active', false)->get();

        // 既存のアクティブな数 + 新しく作成した2つ
        $this->assertCount($existingActiveCount + 2, $activeExamTypes);
        $this->assertGreaterThanOrEqual(1, $inactiveExamTypes->count()); // 少なくとも1つは作成した

        foreach ($activeExamTypes as $examType) {
            $this->assertTrue($examType->is_active);
        }
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function can_create_exam_type_with_valid_data()
    {
        $examTypeData = [
            'code' => 'test_exam',
            'name' => 'テスト試験',
            'description' => 'テスト用の試験です',
            'is_active' => true,
        ];

        $examType = ExamType::create($examTypeData);

        $this->assertDatabaseHas('exam_types', $examTypeData);
        $this->assertEquals($examTypeData['code'], $examType->code);
        $this->assertEquals($examTypeData['name'], $examType->name);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function code_must_be_unique()
    {
        $examType1 = ExamType::factory()->create(['code' => 'unique_code']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ExamType::factory()->create(['code' => 'unique_code']);
    }
}
