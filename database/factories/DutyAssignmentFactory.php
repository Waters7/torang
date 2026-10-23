<?php

namespace Database\Factories;

use App\Models\DutyAssignment;
use App\Models\DutyWeek;
use App\Models\Shift;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DutyAssignment>
 */
class DutyAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'duty_week_id' => DutyWeek::factory(),
            'date' => fn (array $attributes) => DutyWeek::find($attributes['duty_week_id'])->week_start->toDateString(),
            'shift_id' => Shift::factory(),
            'lab_id' => null,
            'staff_id' => Staff::factory(),
        ];
    }
}
