<?php

namespace Database\Seeders;

use App\Actions\Ukom\FinalizeUkomReport;
use App\Actions\Ukom\SaveUkomReport;
use App\Models\ExamOrganizer;
use App\Models\UkomReport;
use App\Services\SettingsService;
use App\Services\Ukom\UkomReportText;
use Illuminate\Database\Seeder;

/**
 * 1 laporan UKOM final sesuai dokumen contoh (SPEC §16): UKOM Nasional Periode 2, D-3 Teknologi Bank Darah,
 * 05 September 2026, total Rp 280.000,00. Penanda tangan diambil dari Pengaturan (tidak di-hardcode).
 * Hanya untuk non-produksi.
 */
class SampleUkomReportSeeder extends Seeder
{
    public const LETTER_NUMBER = 'PP.06.03/F.XXX.20/023/2026';

    public function run(SaveUkomReport $save, FinalizeUkomReport $finalize): void
    {
        if (UkomReport::withTrashed()->where('letter_number', self::LETTER_NUMBER)->exists()) {
            return;
        }

        $organizer = ExamOrganizer::query()->where('name', 'Kolegium TPD')->first()
            ?? ExamOrganizer::factory()->create(['name' => 'Kolegium TPD']);

        $report = $save->create(self::sampleData($organizer), null);
        $finalize->handle($report);
    }

    /**
     * Isian dokumen contoh, dipakai seeder dan test.
     *
     * @return array<string, mixed>
     */
    public static function sampleData(ExamOrganizer $organizer): array
    {
        $settings = app(SettingsService::class);
        $setting = fn (string $key, string $fallback): string => filled($value = $settings->get("reports.ukom.{$key}")) ? (string) $value : $fallback;

        $activity = UkomReportText::activityName('Uji Kompetensi Nasional', 2, 'D-3 Teknologi Bank Darah');

        return [
            'exam_organizer_id' => $organizer->id,
            'exam_name' => 'Uji Kompetensi Nasional',
            'period' => 2,
            'study_program' => 'D-3 Teknologi Bank Darah',
            'exam_date' => '2026-09-05',
            'activity_name' => $activity,
            'auto_number' => false,
            'letter_number' => self::LETTER_NUMBER,
            'letter_place' => $setting('letter_place', 'Manado'),
            'letter_date' => '2026-09-05',
            'attachment_count' => 2,
            'subject' => UkomReportText::subject($activity, '2026-09-05'),
            'recipient_title' => UkomReportText::recipientTitle($organizer->recipient_title_default, 2, 'D-3 Teknologi Bank Darah'),
            'recipient_org' => $organizer->name,
            'recipient_place' => 'tempat',
            'signer_name' => $setting('signer_name', '[Nama Penanda Tangan]'),
            'signer_nip' => $setting('signer_nip', '[NIP 18 digit]'),
            'signer_position' => $setting('signer_position', 'Koordinator CBT Poltekkes Kemenkes Manado'),
            'signer_label' => $setting('signer_label', 'Koordinator CBT'),
            'signer_address_1' => $setting('signer_address_1', '-'),
            'signer_address_2' => $setting('signer_address_2', ''),
            'responsible_role' => $setting('responsible_role', 'Penanggung Jawab Lokasi TUK/Koordinator CBT'),
            'budget_name' => $organizer->budget_name_default,
            'guideline_year' => $organizer->guideline_year_default,
            'responsible_party' => $organizer->responsible_party,
            'bank_account_name' => $organizer->bank_account_name,
            'bank_name' => $organizer->bank_name,
            'bank_account_number' => $organizer->bank_account_number,
            'bank_account_holder' => $organizer->bank_account_holder,
            'items' => [
                ['description' => 'Pembayaran Sewa CBT', 'qty_1' => 2, 'unit_1' => 'unit', 'unit_price' => 40000],
                ['description' => 'Konsumsi', 'qty_1' => 2, 'unit_1' => 'orang', 'qty_2' => 2, 'unit_2' => 'hari', 'note' => 'H-1 dan H', 'unit_price' => 50000],
            ],
        ];
    }
}
