<?php

namespace App\Services\Ukom;

use App\Exceptions\UkomDocumentException;
use App\Models\UkomReport;
use App\Models\UkomReportItem;
use App\Services\SettingsService;
use App\Support\Rupiah;
use App\Support\Terbilang;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Isi template Word laporan UKOM (SPEC §8A.6): clone blok rincian, isi semua placeholder, pastikan tidak ada
 * yang tersisa, lalu simpan ke storage/app/private/reports/ukom/{id}/{slug}.docx.
 */
class UkomReportDocumentService
{
    public function __construct(
        private readonly UkomTemplates $templates,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return string Path DOCX relatif pada disk `local`.
     *
     * @throws UkomDocumentException
     */
    public function generate(UkomReport $report): string
    {
        $report->loadMissing(['items', 'template']);
        $template = $report->template ?? $this->templates->active();

        Settings::setOutputEscapingEnabled(true);

        $processor = new TemplateProcessor($this->templates->path($template));
        $processor->cloneBlock('rincian', 0, true, false, $this->rows($report));
        $processor->setValues($this->variables($report));

        if (($left = $processor->getVariables()) !== []) {
            throw new UkomDocumentException('Placeholder belum terisi: '.implode(', ', array_unique($left)));
        }

        $path = $report->documentDirectory().'/'.$report->fileBaseName().'.docx';
        Storage::disk('local')->makeDirectory($report->documentDirectory());
        $processor->saveAs(Storage::disk('local')->path($path));

        return $path;
    }

    /**
     * @return array<string, string|int>
     */
    public function variables(UkomReport $report): array
    {
        return [
            'nomor_surat' => $report->letter_number,
            'tempat_surat' => $report->letter_place,
            'tanggal_surat' => UkomReportText::letterDate($report->letter_date),
            'lampiran_jumlah' => $report->attachment_count,
            'lampiran_terbilang' => Terbilang::id($report->attachment_count),
            'hal' => $report->subject,
            'tujuan_jabatan' => $report->recipient_title,
            'tujuan_instansi' => $report->recipient_org,
            'tujuan_tempat' => $report->recipient_place,
            'nama_kegiatan' => $report->activity_name,
            'tanggal_kegiatan' => UkomReportText::examDate($report->exam_date),
            'nama_instansi' => (string) $this->settings->get('identity.institution_name'),
            'total_biaya' => Rupiah::format($report->total_amount),
            'judul_ujian' => mb_strtoupper("{$report->exam_name} Periode {$report->period}"),
            'judul_prodi' => mb_strtoupper($report->study_program),
            'ttd_label' => $report->signer_label,
            'ttd_nama' => $report->signer_name,
            'ttd_nip' => $report->signer_nip,
            'ttd_jabatan' => $report->signer_position,
            'ttd_alamat_1' => $report->signer_address_1,
            'ttd_alamat_2' => (string) $report->signer_address_2,
            'peran_penanggung_jawab' => $report->responsible_role,
            'nama_anggaran' => $report->budget_name,
            'tahun_pedoman' => $report->guideline_year,
            'pihak_penyelenggara' => $report->responsible_party,
            'rekening_nama' => $report->bank_account_name,
            'rekening_bank' => $report->bank_name,
            'rekening_nomor' => $report->bank_account_number,
            'rekening_atas_nama' => $report->bank_account_holder,
        ];
    }

    /**
     * @return list<array{rincian_no: int, rincian_uraian: string, rincian_jumlah: string}>
     */
    public function rows(UkomReport $report): array
    {
        return $report->items->values()->map(fn (UkomReportItem $item, int $index): array => [
            'rincian_no' => $index + 1,
            'rincian_uraian' => $item->display_text,
            'rincian_jumlah' => Rupiah::format($item->amount),
        ])->all();
    }
}
