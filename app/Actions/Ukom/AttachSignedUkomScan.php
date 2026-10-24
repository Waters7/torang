<?php

namespace App\Actions\Ukom;

use App\Enums\UkomReportStatus;
use App\Models\UkomReport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Simpan scan laporan bertanda tangan (PDF di disk privat) dan ubah status menjadi Bertanda tangan.
 */
class AttachSignedUkomScan
{
    public function handle(UkomReport $report, string $storedPath): UkomReport
    {
        if (! $report->isLocked()) {
            throw ValidationException::withMessages(['signed_scan_path' => 'Finalkan laporan sebelum mengunggah scan bertanda tangan.']);
        }

        $target = $report->documentDirectory().'/'.$report->fileBaseName().'-ttd.pdf';

        if ($storedPath !== $target) {
            Storage::disk('local')->delete($target);
            Storage::disk('local')->move($storedPath, $target);
        }

        $report->update(['signed_scan_path' => $target, 'status' => UkomReportStatus::Signed]);

        return $report;
    }
}
