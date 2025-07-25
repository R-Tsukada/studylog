<?php

namespace Database\Factories;

use App\Models\StudySession;
use App\Models\SubjectArea;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudySessionFactory extends Factory
{
    protected $model = StudySession::class;

    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $endedAt = (clone $startedAt)->modify('+'.$this->faker->numberBetween(15, 180).' minutes');

        return [
            'user_id' => User::factory(),
            'subject_area_id' => SubjectArea::factory(),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_minutes' => Carbon::parse($startedAt)->diffInMinutes($endedAt),
            'study_comment' => $this->faker->sentence(10),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => null,
            'duration_minutes' => 0,
        ]);
    }

    public function completed(): static
    {
        $startedAt = $this->faker->dateTimeBetween('-7 days', 'now');
        $endedAt = (clone $startedAt)->modify('+'.$this->faker->numberBetween(15, 180).' minutes');

        return $this->state(fn (array $attributes) => [
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_minutes' => Carbon::parse($startedAt)->diffInMinutes($endedAt),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function forSubjectArea(SubjectArea $subjectArea): static
    {
        return $this->state(fn (array $attributes) => [
            'subject_area_id' => $subjectArea->id,
        ]);
    }
}
