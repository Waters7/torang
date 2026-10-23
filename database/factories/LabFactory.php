<?php

namespace Database\Factories;

use App\Enums\DeviceType;
use App\Enums\LabStatus;
use App\Models\Lab;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lab>
 */
class LabFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999);

        return [
            'code' => "LAB-{$number}",
            'name' => "Lab CBT {$number}",
            'location' => 'Gedung CBT Center, Lantai 2',
            'capacity' => 40,
            'color' => fake()->randomElement(['#04B6A3', '#1BBBCB', '#42B97C']),
            'status' => LabStatus::Active,
        ];
    }

    /**
     * Lab dengan sejumlah PC Client berkondisi baik (dan rusak).
     */
    public function withPcs(int $good, int $broken = 0): static
    {
        return $this->afterCreating(function (Lab $lab) use ($good, $broken): void {
            $lab->deviceCounts()->create([
                'device_type' => DeviceType::PcClient,
                'total' => $good + $broken,
                'good' => $good,
                'broken' => $broken,
            ]);
        });
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LabStatus::Maintenance,
        ]);
    }
}
