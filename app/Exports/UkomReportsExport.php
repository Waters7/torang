<?php

namespace App\Exports;

use App\Exports\Sheets\UkomReportsSheet;
use App\Exports\Sheets\UkomYearlySummarySheet;
use App\Models\UkomReport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Rekap laporan UKOM: daftar laporan pada tahun surat terpilih + total biaya per tahun (tanpa arsip).
 */
class UkomReportsExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly int $year) {}

    /**
     * @return list<object>
     */
    public function sheets(): array
    {
        return [
            new UkomReportsSheet($this->year),
            new UkomYearlySummarySheet,
        ];
    }

    /**
     * @return list<array{year: int, reports: int, total: float}>
     */
    public static function yearlyTotals(): array
    {
        return UkomReport::query()
            ->select('letter_year', DB::raw('COUNT(*) as reports'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('letter_year')
            ->orderByDesc('letter_year')
            ->get()
            ->map(fn ($row): array => ['year' => (int) $row->letter_year, 'reports' => (int) $row->reports, 'total' => round((float) $row->total, 2)])
            ->all();
    }
}
