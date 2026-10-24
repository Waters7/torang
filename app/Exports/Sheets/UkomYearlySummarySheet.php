<?php

namespace App\Exports\Sheets;

use App\Exports\UkomReportsExport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UkomYearlySummarySheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Rekap per tahun';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Tahun', 'Jumlah laporan', 'Total biaya'];
    }

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        return array_map(fn (array $row): array => [$row['year'], $row['reports'], $row['total']], UkomReportsExport::yearlyTotals());
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return ['C' => UkomReportsSheet::MONEY_FORMAT];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
