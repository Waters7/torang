<?php

namespace App\Actions\Ukom;

use App\Exceptions\UkomDocumentException;
use App\Jobs\ConvertUkomReportToPdf;
use App\Models\UkomReport;
use App\Services\Ukom\LibreOfficeConverter;
use App\Services\Ukom\UkomReportDocumentService;
use Illuminate\Support\Facades\Storage;

/**
 * Buat ulang DOCX laporan lalu antrekan konversi PDF (bila LibreOffice tersedia). PDF lama dibuang
 * agar pratinjau tidak menampilkan isi yang sudah usang.
 */
class GenerateUkomDocuments
{
    public function __construct(
        private readonly UkomReportDocumentService $documents,
        private readonly LibreOfficeConverter $converter,
    ) {}

    /**
     * Kegagalan membuat DOCX (mis. template rusak) tidak membatalkan penyimpanan; pesannya disimpan di pdf_error
     * dan tampil di halaman detail.
     */
    public function handle(UkomReport $report): UkomReport
    {
        $old = array_filter([$report->docx_path, $report->pdf_path]);

        try {
            $docx = $this->documents->generate($report);
        } catch (UkomDocumentException $exception) {
            $report->forceFill(['docx_path' => null, 'pdf_path' => null, 'pdf_error' => str($exception->getMessage())->limit(250)->toString()])->saveQuietly();

            return $report;
        }

        Storage::disk('local')->delete(array_diff($old, [$docx]));

        $report->forceFill(['docx_path' => $docx, 'pdf_path' => null, 'pdf_error' => null])->saveQuietly();

        if ($this->converter->available()) {
            ConvertUkomReportToPdf::dispatch($report);
        }

        return $report;
    }
}
