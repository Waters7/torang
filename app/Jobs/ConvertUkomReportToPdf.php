<?php

namespace App\Jobs;

use App\Exceptions\UkomDocumentException;
use App\Models\UkomReport;
use App\Services\Ukom\LibreOfficeConverter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Konversi DOCX laporan UKOM ke PDF di queue `default`. Gagal = pesan disimpan di pdf_error (DOCX tetap tersedia).
 */
class ConvertUkomReportToPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public UkomReport $report)
    {
        $this->onQueue('default');
    }

    public function handle(LibreOfficeConverter $converter): void
    {
        $report = $this->report->fresh();

        if (! $report?->docx_path || ! $converter->available()) {
            return;
        }

        try {
            $pdf = $converter->convert(Storage::disk('local')->path($report->docx_path));

            $report->forceFill([
                'pdf_path' => $report->documentDirectory().'/'.basename($pdf),
                'pdf_error' => null,
            ])->saveQuietly();
        } catch (UkomDocumentException $exception) {
            $report->forceFill(['pdf_path' => null, 'pdf_error' => str($exception->getMessage())->limit(250)->toString()])->saveQuietly();
        }
    }
}
