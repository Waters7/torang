<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor Excel laporan CBT/SLA dari App\Services\Reports\OperationalReports: judul, periode, catatan,
 * lalu tabel. Teks yang diawali karakter formula diberi apostrof agar tidak dieksekusi spreadsheet.
 */
class OperationalReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(private readonly array $report) {}

    /**
     * @return list<list<string>>
     */
    public function headings(): array
    {
        return [
            [$this->report['title']],
            [$this->report['start'].' s.d. '.$this->report['end']],
            [$this->report['note']],
            $this->report['headings'],
        ];
    }

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        return array_map(fn (array $row): array => array_map(function (mixed $value): mixed {
            if ($value === null) {
                return 'Tidak ada data';
            }

            // Jangan izinkan teks dari master data menjadi formula spreadsheet.
            return is_string($value) && preg_match('/^[=+\-@\t\r\n]/', $value) ? "'".$value : $value;
        }, $row), $this->report['rows']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $last = $sheet->getHighestColumn();

        foreach ([1, 2, 3] as $row) {
            $sheet->mergeCells("A{$row}:{$last}{$row}");
        }

        $sheet->getStyle('A3')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(3)->setRowHeight(60);

        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            4 => ['font' => ['bold' => true]],
        ];
    }
}
