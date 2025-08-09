<?php

namespace Tests\Unit\Models;

use App\Models\ExamType;
use App\Models\StudyGoal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudyGoalTest extends TestCase
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
    public function study_goal_has_correct_fillable_attributes()
    {
        $studyGoal = new StudyGoal;
        $expected = ['user_id', 'exam_type_id', 'daily_minutes_goal', 'weekly_minutes_goal', 'exam_date', 'is_active'];

        $this->assertEquals($expected, $studyGoal->getFillable());
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function study_goal_casts_attributes_correctly()
    {
        $studyGoal = StudyGoal::factory()->create([
            'daily_minutes_goal' => '60',
            'weekly_minutes_goal' => '420',
            'exam_date' => '2024-12-31',
            'is_active' => 1,
        ]);

        $this->assertIsInt($studyGoal->daily_minutes_goal);
        $this->assertIsInt($studyGoal->weekly_minutes_goal);
        $this->assertInstanceOf(Carbon::class, $studyGoal->exam_date);
        $this->assertIsBool($studyGoal->is_active);
        $this->assertEquals(60, $studyGoal->daily_minutes_goal);
        $this->assertEquals(420, $studyGoal->weekly_minutes_goal);
        $this->assertTrue($studyGoal->is_active);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function study_goal_belongs_to_user()
    {
        $user = User::factory()->create();
        $studyGoal = StudyGoal::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $studyGoal->user);
        $this->assertEquals($user->id, $studyGoal->user->id);
        $this->assertEquals($user->nickname, $studyGoal->user->nickname);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function study_goal_belongs_to_exam_type()
    {
        $examType = ExamType::factory()->create();
        $studyGoal = StudyGoal::factory()->create(['exam_type_id' => $examType->id]);

        $this->assertInstanceOf(ExamType::class, $studyGoal->examType);
        $this->assertEquals($examType->id, $studyGoal->examType->id);
        $this->assertEquals($examType->name, $studyGoal->examType->name);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function active_scope_returns_only_active_goals()
    {
        $user = User::factory()->create();
        $examType = ExamType::factory()->create();

        StudyGoal::factory()->create([
            'user_id' => $user->id,
            'exam_type_id' => $examType->id,
            'is_active' => true,
        ]);

        StudyGoal::factory()->create([
            'user_id' => $user->id,
            'exam_type_id' => $examType->id,
            'is_active' => false,
        ]);

        $activeGoals = StudyGoal::active()->get();

        $this->assertGreaterThan(0, $activeGoals->count());
        foreach ($activeGoals as $goal) {
            $this->assertTrue($goal->is_active);
        }
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function by_user_scope_filters_by_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $examType = ExamType::factory()->create();

        StudyGoal::factory()->create(['user_id' => $user1->id, 'exam_type_id' => $examType->id]);
        StudyGoal::factory()->create(['user_id' => $user2->id, 'exam_type_id' => $examType->id]);

        $user1Goals = StudyGoal::byUser($user1->id)->get();

        $this->assertGreaterThan(0, $user1Goals->count());
        foreach ($user1Goals as $goal) {
            $this->assertEquals($user1->id, $goal->user_id);
        }
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function for_exam_type_scope_filters_by_exam_type()
    {
        $user = User::factory()->create();
        $examType1 = ExamType::factory()->create();
        $examType2 = ExamType::factory()->create();

        StudyGoal::factory()->create(['user_id' => $user->id, 'exam_type_id' => $examType1->id]);
        StudyGoal::factory()->create(['user_id' => $user->id, 'exam_type_id' => $examType2->id]);

        $examType1Goals = StudyGoal::forExamType($examType1->id)->get();

        $this->assertGreaterThan(0, $examType1Goals->count());
        foreach ($examType1Goals as $goal) {
            $this->assertEquals($examType1->id, $goal->exam_type_id);
        }
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function can_create_study_goal_with_valid_data()
    {
        $user = User::factory()->create();
        $examType = ExamType::factory()->create();

        $goalData = [
            'user_id' => $user->id,
            'exam_type_id' => $examType->id,
            'daily_minutes_goal' => 120,
            'weekly_minutes_goal' => 840,
            'exam_date' => Carbon::create(2024, 12, 31),
            'is_active' => true,
        ];

        $studyGoal = StudyGoal::create($goalData);

        $this->assertDatabaseHas('study_goals', [
            'user_id' => $goalData['user_id'],
            'exam_type_id' => $goalData['exam_type_id'],
            'daily_minutes_goal' => $goalData['daily_minutes_goal'],
            'weekly_minutes_goal' => $goalData['weekly_minutes_goal'],
            'exam_date' => $goalData['exam_date']->format('Y-m-d 00:00:00'),
            'is_active' => 1,
        ]);

        $this->assertEquals($goalData['daily_minutes_goal'], $studyGoal->daily_minutes_goal);
        $this->assertEquals($goalData['weekly_minutes_goal'], $studyGoal->weekly_minutes_goal);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function can_create_study_goal_without_optional_fields()
    {
        $user = User::factory()->create();
        $examType = ExamType::factory()->create();

        $goalData = [
            'user_id' => $user->id,
            'exam_type_id' => $examType->id,
            'is_active' => true,
        ];

        $studyGoal = StudyGoal::create($goalData);

        $this->assertDatabaseHas('study_goals', $goalData);
        $this->assertNull($studyGoal->daily_minutes_goal);
        $this->assertNull($studyGoal->weekly_minutes_goal);
        $this->assertNull($studyGoal->exam_date);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function can_have_multiple_inactive_goals_for_same_user_and_exam_type()
    {
        $user = User::factory()->create();
        $examType = ExamType::factory()->create();

        StudyGoal::factory()->create([
            'user_id' => $user->id,
            'exam_type_id' => $examType->id,
            'is_active' => false,
        ]);

        StudyGoal::factory()->create([
            'user_id' => $user->id,
            'exam_type_id' => $examType->id,
            'is_active' => false,
        ]);

        $goals = StudyGoal::where('user_id', $user->id)
            ->where('exam_type_id', $examType->id)
            ->where('is_active', false)
            ->get();

        $this->assertCount(2, $goals);
    }

    
use PHPUnit\Framework\Attributes\Test;

/** @test */
    #[Test]
    public function exam_date_can_be_null()
    {
        $user = User::factory()->create();
        $examType = ExamType::factory()->create();

        $studyGoal = StudyGoal::factory()->create([
            'user_id' => $user->id,
            'exam_type_id' => $examType->id,
            'exam_date' => null,
        ]);

        $this->assertNull($studyGoal->exam_date);
    }
}
