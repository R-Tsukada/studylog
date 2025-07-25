<?php

namespace Database\Factories;

use App\Models\ExamType;
use App\Models\SubjectArea;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectAreaFactory extends Factory
{
    protected $model = SubjectArea::class;

    public function definition(): array
    {
        return [
            'exam_type_id' => ExamType::factory(),
            'code' => $this->faker->unique()->slug(2),
            'name' => $this->faker->sentence(2),
            'description' => $this->faker->paragraph(),
            'sort_order' => $this->faker->numberBetween(1, 10),
            'is_active' => true,
            'user_id' => null,
            'is_system' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forExamType(ExamType $examType): static
    {
        return $this->state(fn (array $attributes) => [
            'exam_type_id' => $examType->id,
        ]);
    }
}
