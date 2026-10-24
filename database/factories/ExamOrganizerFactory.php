<?php

namespace Database\Factories;

use App\Models\ExamOrganizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamOrganizer>
 */
class ExamOrganizerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Kolegium '.fake()->unique()->lexify('???');

        return [
            'name' => $name,
            'recipient_title_default' => 'Ketua Panitia Ujian Kompetensi Nasional',
            'responsible_party' => "Tim Ad Hoc {$name}",
            'bank_account_name' => "Tim Ad Hoc {$name}",
            'bank_name' => 'MANDIRI',
            'bank_account_number' => fake()->numerify('#############'),
            'bank_account_holder' => "Tim Ad Hoc {$name}",
            'budget_name_default' => 'UP UK Nakes Tahun 2022',
            'guideline_year_default' => '2022',
            'rate_cbt_per_unit' => 40000,
            'rate_meal_per_person_day' => 50000,
            'is_active' => true,
        ];
    }
}
