<?php

namespace Database\Factories;

use App\Enums\UkomReportStatus;
use App\Models\ExamOrganizer;
use App\Models\UkomReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Laporan tanpa rincian & dokumen; untuk alur lengkap pakai App\Actions\Ukom\SaveUkomReport.
 *
 * @extends Factory<UkomReport>
 */
class UkomReportFactory extends Factory
{
    private static int $sequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sequence = ++self::$sequence;
        $year = (int) today()->format('Y');

        return [
            'exam_organizer_id' => ExamOrganizer::factory(),
            'status' => UkomReportStatus::Draft,
            'exam_name' => 'Uji Kompetensi Nasional',
            'period' => 2,
            'study_program' => 'D-3 Teknologi Bank Darah',
            'exam_date' => today()->subDays(3),
            'activity_name' => 'Uji Kompetensi Nasional Periode 2 Mahasiswa D-3 Teknologi Bank Darah',
            'letter_number' => sprintf('PP.06.03/F.XXX.20/%03d/%d', 900 + $sequence, $year),
            'letter_sequence' => 900 + $sequence,
            'letter_year' => $year,
            'letter_place' => 'Manado',
            'letter_date' => today(),
            'attachment_count' => 2,
            'subject' => 'Laporan dan Bukti Pertanggungjawaban',
            'recipient_title' => 'Ketua Panitia Ujian Kompetensi Nasional',
            'recipient_org' => 'Kolegium TPD',
            'recipient_place' => 'tempat',
            'signer_name' => 'Penanda Tangan',
            'signer_nip' => '199001012020121001',
            'signer_position' => 'Koordinator CBT Poltekkes Kemenkes Manado',
            'signer_label' => 'Koordinator CBT',
            'signer_address_1' => 'Jl. Contoh No. 1',
            'responsible_role' => 'Penanggung Jawab Lokasi TUK/Koordinator CBT',
            'budget_name' => 'UP UK Nakes Tahun 2022',
            'guideline_year' => '2022',
            'responsible_party' => 'Tim Ad Hoc Kolegium TPD',
            'bank_account_name' => 'Tim Ad Hoc Kolegium TPD',
            'bank_name' => 'MANDIRI',
            'bank_account_number' => '0000000000000',
            'bank_account_holder' => 'Tim Ad Hoc Kolegium TPD',
            'total_amount' => 280000,
        ];
    }

    public function final(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UkomReportStatus::Final,
            'finalized_at' => now(),
        ]);
    }
}
