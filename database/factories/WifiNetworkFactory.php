<?php

namespace Database\Factories;

use App\Enums\WifiAudience;
use App\Enums\WifiSecurity;
use App\Models\WifiNetwork;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WifiNetwork>
 */
class WifiNetworkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ssid' => 'POLTEKKES-'.Str::upper(fake()->unique()->lexify('????')),
            'location' => 'Gedung '.fake()->randomElement(['A', 'B', 'C']).', Lantai '.fake()->numberBetween(1, 3),
            'audience' => WifiAudience::Student,
            'security' => WifiSecurity::Wpa2,
            'password' => Str::password(14, symbols: false),
            'vlan' => fake()->numberBetween(10, 200),
            'password_changed_at' => today()->subDays(30),
            'is_active' => true,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'security' => WifiSecurity::Open,
            'password' => null,
            'password_changed_at' => null,
            'audience' => WifiAudience::Guest,
        ]);
    }

    public function changedDaysAgo(int $days, ?int $rotationDays = null): static
    {
        return $this->state(fn (array $attributes) => [
            'password_changed_at' => today()->subDays($days),
            'rotation_days' => $rotationDays,
        ]);
    }
}
