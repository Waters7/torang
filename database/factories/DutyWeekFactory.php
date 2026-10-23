<?php

namespace Database\Factories;

use App\Enums\DutyWeekStatus;
use App\Models\DutyWeek;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DutyWeek>
 */
class DutyWeekFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'week_start' => now()->startOfWeek()->toDateString(),
            'status' => DutyWeekStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function startingAt(string $monday): static
    {
        return $this->state(fn (array $attributes) => [
            'week_start' => $monday,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DutyWeekStatus::Published,
            'published_at' => now(),
        ]);
    }
}
