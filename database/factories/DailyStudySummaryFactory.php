<?php

namespace Database\Factories;

use App\Models\DailyStudySummary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyStudySummaryFactory extends Factory
{
    protected $model = DailyStudySummary::class;

    public function definition(): array
    {
        $totalMinutes = $this->faker->numberBetween(30, 240);
        $sessionCount = $this->faker->numberBetween(1, 4);

        return [
            'user_id' => User::factory(),
            'study_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'total_minutes' => $totalMinutes,
            'session_count' => $sessionCount,
            'subject_breakdown' => [
                'テストの基礎' => (int) ($totalMinutes * 0.4),
                'テスト技法' => (int) ($totalMinutes * 0.6),
            ],
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function forDate(Carbon $date): static
    {
        return $this->state(fn (array $attributes) => [
            'study_date' => $date->format('Y-m-d'),
        ]);
    }

    public function withMinutes(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'total_minutes' => $minutes,
            'subject_breakdown' => [
                'テスト' => $minutes,
            ],
        ]);
    }

    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_minutes' => 0,
            'session_count' => 0,
            'subject_breakdown' => [],
        ]);
    }
}
