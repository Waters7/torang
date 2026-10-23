<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageLog>
 */
class UsageLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->completed(),
            'attendees_count' => 28,
            'devices_used' => 30,
            'devices_problem' => 0,
            'filled_by' => User::factory(),
        ];
    }
}
