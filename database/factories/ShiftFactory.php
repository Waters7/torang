<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Pagi',
            'start_time' => '07:30',
            'end_time' => '12:00',
            'sort_order' => 1,
            'is_active' => true,
        ];
    }

    public function afternoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Siang',
            'start_time' => '12:00',
            'end_time' => '16:00',
            'sort_order' => 2,
        ]);
    }
}
