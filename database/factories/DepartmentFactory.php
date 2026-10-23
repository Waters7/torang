<?php

namespace Database\Factories;

use App\Enums\DepartmentType;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Keperawatan', 'Kebidanan', 'Gizi', 'Farmasi', 'Kesehatan Gigi', 'Kesehatan Lingkungan', 'Teknologi Laboratorium Medis']);

        return [
            'name' => "Jurusan {$name}",
            'short_name' => $name,
            'type' => DepartmentType::Department,
            'sort_order' => 0,
        ];
    }
}
