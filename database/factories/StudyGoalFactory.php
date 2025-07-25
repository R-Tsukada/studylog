<?php

namespace Database\Factories;

use App\Models\ExamType;
use App\Models\StudyGoal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudyGoalFactory extends Factory
{
    protected $model = StudyGoal::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exam_type_id' => ExamType::factory(),
            'daily_minutes_goal' => $this->faker->numberBetween(30, 180),
            'weekly_minutes_goal' => $this->faker->numberBetween(210, 1260), // 3.5-21時間
            'exam_date' => $this->faker->optional(0.7)->dateTimeBetween('+1 month', '+1 year'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function forExamType(ExamType $examType): static
    {
        return $this->state(fn (array $attributes) => [
            'exam_type_id' => $examType->id,
        ]);
    }

    public function withDailyGoal(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'daily_minutes_goal' => $minutes,
            'weekly_minutes_goal' => $minutes * 7,
        ]);
    }

    public function withWeeklyGoal(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'weekly_minutes_goal' => $minutes,
        ]);
    }

    public function withExamDate(Carbon $date): static
    {
        return $this->state(fn (array $attributes) => [
            'exam_date' => $date,
        ]);
    }

    public function withoutGoals(): static
    {
        return $this->state(fn (array $attributes) => [
            'daily_minutes_goal' => null,
            'weekly_minutes_goal' => null,
            'exam_date' => null,
        ]);
    }
}
