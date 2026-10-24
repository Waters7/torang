<?php

namespace App\Actions\Ukom;

use App\Enums\UkomReportStatus;
use App\Models\UkomReport;
use Illuminate\Validation\ValidationException;

/**
 * Finalkan laporan: isi & nomor surat dikunci, dokumen dibuat ulang dengan template versi yang tercatat.
 */
class FinalizeUkomReport
{
    public function __construct(private readonly GenerateUkomDocuments $documents) {}

    public function handle(UkomReport $report): UkomReport
    {
        if ($report->status !== UkomReportStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Hanya draf yang bisa difinalkan.']);
        }

        $report->update(['status' => UkomReportStatus::Final, 'finalized_at' => now()]);

        return $this->documents->handle($report);
    }
}
