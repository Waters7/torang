<?php

namespace Database\Factories;

use App\Enums\MonitorGroup;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Router '.fake()->unique()->randomElement(['Keperawatan', 'Kebidanan', 'Gizi', 'Farmasi', 'Kesling', 'TLM', 'Kesgi', 'Direktorat']),
            'group' => MonitorGroup::Network,
            'type' => MonitorType::Icmp,
            'host' => '203.0.113.'.fake()->numberBetween(1, 254),
            'isp' => 'Telkom Indonesia',
            'bandwidth' => '100 Mbps',
            'is_public' => true,
            'is_active' => true,
        ];
    }

    public function tcp(int $port = 443): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MonitorType::Tcp,
            'port' => $port,
        ]);
    }

    public function application(string $url = 'https://example.ac.id/'): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Website '.fake()->unique()->word(),
            'group' => MonitorGroup::Application,
            'type' => MonitorType::Http,
            'host' => null,
            'url' => $url,
            'category' => 'Website',
            'isp' => null,
            'bandwidth' => null,
        ]);
    }

    public function status(MonitorStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => $status,
            'last_checked_at' => now(),
            'last_status_change_at' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
