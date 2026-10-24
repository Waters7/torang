<?php

namespace Database\Seeders;

use App\Models\ExamOrganizer;
use Illuminate\Database\Seeder;

class ExamOrganizerSeeder extends Seeder
{
    /**
     * Kolegium TPD sesuai dokumen contoh laporan UKOM (SPEC §8A.3, §16).
     */
    public function run(): void
    {
        ExamOrganizer::firstOrCreate(['name' => 'Kolegium TPD'], [
            'recipient_title_default' => 'Ketua Panitia Ujian Kompetensi Nasional',
            'responsible_party' => 'Tim Ad Hoc Kolegium TPD',
            'bank_account_name' => 'Tim Ad Hoc Kolegium TPD',
            'bank_name' => 'MANDIRI',
            'bank_account_number' => '1260013772123',
            'bank_account_holder' => 'Tim Ad Hoc Kolegium TPD',
            'budget_name_default' => 'UP UK Nakes Tahun 2022',
            'guideline_year_default' => '2022',
            'rate_cbt_per_unit' => 40000,
            'rate_meal_per_person_day' => 50000,
            'is_active' => true,
        ]);
    }
}
