<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\ExamType;
use App\Models\SubjectArea;
use App\Models\StudyGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExamTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->seed();
    }

    /** @test */
    public function exam_type_has_correct_fillable_attributes()
    {
        $examType = new ExamType();
        $expected = [
            'code', 'name', 'description', 'is_active',
            'user_id', 'is_system', 'exam_date', 'exam_notes', 'color'
        ];
        
        $this->assertEquals($expected, $examType->getFillable());
    }

    /** @test */
    public function exam_type_casts_is_active_to_boolean()
    {
        $examType = ExamType::factory()->create(['is_active' => 1]);
        
        $this->assertIsBool($examType->is_active);
        $this->assertTrue($examType->is_active);
    }

    /** @test */
    public function exam_type_casts_is_system_to_boolean()
    {
        $examType = ExamType::factory()->create(['is_system' => 1]);
        
        $this->assertIsBool($examType->is_system);
        $this->assertTrue($examType->is_system);
    }

    /** @test */
    public function exam_type_casts_exam_date_to_date()
    {
        $examType = ExamType::factory()->create(['exam_date' => '2025-06-15']);
        
        $this->assertInstanceOf(\Carbon\Carbon::class, $examType->exam_date);
        $this->assertEquals('2025-06-15', $examType->exam_date->format('Y-m-d'));
    }

    /** @test */
    public function exam_type_has_many_subject_areas()
    {
        $examType = ExamType::factory()->create();
        $subjectArea = SubjectArea::factory()->create(['exam_type_id' => $examType->id]);
        
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $examType->subjectAreas);
        $this->assertInstanceOf(SubjectArea::class, $examType->subjectAreas->first());
        $this->assertEquals($subjectArea->id, $examType->subjectAreas->first()->id);
    }

    /** @test */
    public function exam_type_has_many_study_goals()
    {
        $examType = ExamType::factory()->create();
        $studyGoal = StudyGoal::factory()->create(['exam_type_id' => $examType->id]);
        
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $examType->studyGoals);
        $this->assertInstanceOf(StudyGoal::class, $examType->studyGoals->first());
        $this->assertEquals($studyGoal->id, $examType->studyGoals->first()->id);
    }

    /** @test */
    public function active_scope_returns_only_active_exam_types()
    {
        ExamType::factory()->create(['is_active' => true]);
        ExamType::factory()->create(['is_active' => false]);
        
        $activeExamTypes = ExamType::active()->get();
        
        $this->assertCount(3, $activeExamTypes); // 2つのシーダー + 1つのファクトリー
        foreach ($activeExamTypes as $examType) {
            $this->assertTrue($examType->is_active);
        }
    }

    /** @test */
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

    /** @test */
    public function code_must_be_unique()
    {
        $examType1 = ExamType::factory()->create(['code' => 'unique_code']);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        ExamType::factory()->create(['code' => 'unique_code']);
    }
} 