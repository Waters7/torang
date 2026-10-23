<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nip' => fake()->unique()->numerify('19##############'),
            'name' => fake()->name(),
            'rank' => fake()->randomElement(['III/a', 'III/b', 'III/c', 'IV/a']),
            'position' => 'Dosen Lektor (JFT)',
            'placement' => 'Direktorat / Unit',
            'office_email' => fake()->unique()->userName().'@kemkes.go.id',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
