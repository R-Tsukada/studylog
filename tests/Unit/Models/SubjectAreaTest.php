<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\ExamType;
use App\Models\SubjectArea;
use App\Models\StudySession;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubjectAreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->seed();
    }

    /** @test */
    public function subject_area_has_correct_fillable_attributes()
    {
        $subjectArea = new SubjectArea();
        $expected = [
            'exam_type_id', 'code', 'name', 'description', 
            'sort_order', 'is_active', 'user_id', 'is_system'
        ];
        
        $this->assertEquals($expected, $subjectArea->getFillable());
    }

    /** @test */
    public function subject_area_casts_attributes_correctly()
    {
        $subjectArea = SubjectArea::factory()->create([
            'is_active' => 1,
            'is_system' => 1,
            'sort_order' => '5'
        ]);
        
        $this->assertIsBool($subjectArea->is_active);
        $this->assertIsBool($subjectArea->is_system);
        $this->assertIsInt($subjectArea->sort_order);
        $this->assertTrue($subjectArea->is_active);
        $this->assertTrue($subjectArea->is_system);
        $this->assertEquals(5, $subjectArea->sort_order);
    }

    /** @test */
    public function subject_area_belongs_to_exam_type()
    {
        $examType = ExamType::factory()->create();
        $subjectArea = SubjectArea::factory()->create(['exam_type_id' => $examType->id]);
        
        $this->assertInstanceOf(ExamType::class, $subjectArea->examType);
        $this->assertEquals($examType->id, $subjectArea->examType->id);
        $this->assertEquals($examType->name, $subjectArea->examType->name);
    }

    /** @test */
    public function subject_area_has_many_study_sessions()
    {
        $subjectArea = SubjectArea::factory()->create();
        $studySession = StudySession::factory()->create(['subject_area_id' => $subjectArea->id]);
        
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $subjectArea->studySessions);
        $this->assertInstanceOf(StudySession::class, $subjectArea->studySessions->first());
        $this->assertEquals($studySession->id, $subjectArea->studySessions->first()->id);
    }

    /** @test */
    public function active_scope_returns_only_active_subject_areas()
    {
        $examType = ExamType::factory()->create();
        SubjectArea::factory()->create(['exam_type_id' => $examType->id, 'is_active' => true]);
        SubjectArea::factory()->create(['exam_type_id' => $examType->id, 'is_active' => false]);
        
        $activeSubjectAreas = SubjectArea::active()->get();
        
        $this->assertGreaterThan(0, $activeSubjectAreas->count());
        foreach ($activeSubjectAreas as $subjectArea) {
            $this->assertTrue($subjectArea->is_active);
        }
    }

    /** @test */
    public function ordered_scope_returns_subject_areas_in_sort_order()
    {
        $examType = ExamType::factory()->create();
        SubjectArea::factory()->create(['exam_type_id' => $examType->id, 'sort_order' => 3]);
        SubjectArea::factory()->create(['exam_type_id' => $examType->id, 'sort_order' => 1]);
        SubjectArea::factory()->create(['exam_type_id' => $examType->id, 'sort_order' => 2]);
        
        $orderedSubjectAreas = SubjectArea::ordered()->where('exam_type_id', $examType->id)->get();
        
        $this->assertEquals(1, $orderedSubjectAreas->first()->sort_order);
        $this->assertEquals(3, $orderedSubjectAreas->last()->sort_order);
    }

    /** @test */
    public function can_create_subject_area_with_valid_data()
    {
        $examType = ExamType::factory()->create();
        $subjectAreaData = [
            'exam_type_id' => $examType->id,
            'code' => 'test_subject',
            'name' => 'テスト分野',
            'description' => 'テスト用の分野です',
            'sort_order' => 1,
            'is_active' => true,
        ];
        
        $subjectArea = SubjectArea::create($subjectAreaData);
        
        $this->assertDatabaseHas('subject_areas', $subjectAreaData);
        $this->assertEquals($subjectAreaData['code'], $subjectArea->code);
        $this->assertEquals($subjectAreaData['name'], $subjectArea->name);
    }

    /** @test */
    public function exam_type_id_and_code_combination_must_be_unique()
    {
        $examType = ExamType::factory()->create();
        SubjectArea::factory()->create([
            'exam_type_id' => $examType->id,
            'code' => 'unique_code'
        ]);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        SubjectArea::factory()->create([
            'exam_type_id' => $examType->id,
            'code' => 'unique_code'
        ]);
    }
} 