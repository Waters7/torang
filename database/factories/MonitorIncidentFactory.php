<?php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\MonitorIncident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitorIncident>
 */
class MonitorIncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'monitor_id' => Monitor::factory(),
            'started_at' => now()->subHour(),
        ];
    }

    public function resolved(int $minutes = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'resolved_at' => now(),
            'duration_seconds' => $minutes * 60,
        ]);
    }
}
