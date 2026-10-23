<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'nip' => fake()->numerify('19##############'),
            'position' => 'Pranata Komputer',
            'phone' => fake()->numerify('08##########'),
            'telegram_chat_id' => null,
            'is_active' => true,
        ];
    }

    public function withTelegram(?string $chatId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'telegram_chat_id' => $chatId ?? fake()->numerify('#########'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
