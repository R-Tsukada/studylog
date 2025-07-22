<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PomodoroSession>
 */
class PomodoroSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sessionType = $this->faker->randomElement(['focus', 'short_break', 'long_break']);
        $plannedDuration = match($sessionType) {
            'focus' => $this->faker->randomElement([15, 25, 50]),
            'short_break' => $this->faker->randomElement([5, 10, 15]),
            'long_break' => $this->faker->randomElement([15, 20, 30]),
        };
        
        $startedAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $isCompleted = $this->faker->boolean(80); // 80%の確率で完了
        $wasInterrupted = $isCompleted ? $this->faker->boolean(20) : $this->faker->boolean(50);
        
        $actualDuration = null;
        $completedAt = null;
        
        if ($isCompleted) {
            $actualDuration = $wasInterrupted 
                ? $this->faker->numberBetween(1, $plannedDuration - 1)
                : $this->faker->numberBetween($plannedDuration - 2, $plannedDuration + 2);
            $completedAt = \Carbon\Carbon::parse($startedAt)->addMinutes($actualDuration);
        }

        return [
            'session_type' => $sessionType,
            'planned_duration' => $plannedDuration,
            'actual_duration' => $actualDuration,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'is_completed' => $isCompleted,
            'was_interrupted' => $wasInterrupted,
            'settings' => [
                'focus_duration' => 25,
                'short_break_duration' => 5,
                'long_break_duration' => 15,
                'auto_start_break' => $this->faker->boolean(),
                'auto_start_focus' => $this->faker->boolean(),
                'sound_enabled' => $this->faker->boolean(),
            ],
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    public function focus(): static
    {
        return $this->state(fn (array $attributes) => [
            'session_type' => 'focus',
            'planned_duration' => $this->faker->randomElement([15, 25, 50]),
        ]);
    }

    public function shortBreak(): static
    {
        return $this->state(fn (array $attributes) => [
            'session_type' => 'short_break',
            'planned_duration' => $this->faker->randomElement([5, 10, 15]),
        ]);
    }

    public function longBreak(): static
    {
        return $this->state(fn (array $attributes) => [
            'session_type' => 'long_break',
            'planned_duration' => $this->faker->randomElement([15, 20, 30]),
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $actualDuration = $this->faker->numberBetween(
                $attributes['planned_duration'] - 2, 
                $attributes['planned_duration'] + 2
            );
            $startedAt = $attributes['started_at'] ?? now()->subMinutes($actualDuration);
            
            return [
                'actual_duration' => $actualDuration,
                'completed_at' => \Carbon\Carbon::parse($startedAt)->addMinutes($actualDuration),
                'is_completed' => true,
                'was_interrupted' => false,
            ];
        });
    }

    public function interrupted(): static
    {
        return $this->state(function (array $attributes) {
            $actualDuration = $this->faker->numberBetween(1, $attributes['planned_duration'] - 1);
            $startedAt = $attributes['started_at'] ?? now()->subMinutes($actualDuration);
            
            return [
                'actual_duration' => $actualDuration,
                'completed_at' => \Carbon\Carbon::parse($startedAt)->addMinutes($actualDuration),
                'is_completed' => true,
                'was_interrupted' => true,
            ];
        });
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'actual_duration' => null,
            'completed_at' => null,
            'is_completed' => false,
            'was_interrupted' => false,
            'started_at' => now()->subMinutes($this->faker->numberBetween(1, 10)),
        ]);
    }
}
