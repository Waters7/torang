<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OperationalReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(private readonly array $report) {}

    public function headings(): array
    {
        return [[$this->report['title']], [$this->report['start'].' s.d. '.$this->report['end']], [$this->report['note']], $this->report['headings']];
    }

    public function array(): array
    {
        return array_map(fn ($row) => array_map(function ($value) {
            if ($value === null) {
                return 'Tidak ada data';
            }

            // Jangan izinkan teks dari master data menjadi formula spreadsheet.
            return is_string($value) && preg_match('/^[=+\-@\t\r\n]/', $value) ? "'".$value : $value;
        }, $row), $this->report['rows']);
    }

    public function styles(Worksheet $sheet): array
    {
        $last = $sheet->getHighestColumn();
        foreach ([1, 2, 3] as $row) {
            $sheet->mergeCells("A{$row}:{$last}{$row}");
        }
        $sheet->getStyle('A3')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(3)->setRowHeight(60);

        return [1 => ['font' => ['bold' => true, 'size' => 16]], 4 => ['font' => ['bold' => true]]];
    }
}
