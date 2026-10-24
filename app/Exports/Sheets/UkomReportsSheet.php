<?php

namespace App\Exports\Sheets;

use App\Models\UkomReport;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UkomReportsSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    public const MONEY_FORMAT = '"Rp "#,##0.00';

    public function __construct(private readonly int $year) {}

    public function title(): string
    {
        return "Laporan {$this->year}";
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['No', 'Nomor surat', 'Tanggal surat', 'Kegiatan', 'Jenjang & prodi', 'Periode', 'Tanggal pelaksanaan', 'Kolegium', 'Total biaya', 'Status'];
    }

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        /** @var Collection<int, UkomReport> $reports */
        $reports = UkomReport::query()
            ->with('organizer')
            ->where('letter_year', $this->year)
            ->orderBy('letter_date')
            ->orderBy('letter_sequence')
            ->get();

        return [
            ...$reports->values()->map(fn (UkomReport $report, int $index): array => [
                $index + 1,
                $report->letter_number,
                Date::dateTimeToExcel($report->letter_date),
                $report->activity_name,
                $report->study_program,
                $report->period,
                Date::dateTimeToExcel($report->exam_date),
                $report->organizer->name,
                (float) $report->total_amount,
                $report->status->label(),
            ])->all(),
            ['Total', null, null, null, null, null, null, null, round((float) $reports->sum('total_amount'), 2), null],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'C' => HostedServicesSheet::DATE_FORMAT,
            'G' => HostedServicesSheet::DATE_FORMAT,
            'I' => self::MONEY_FORMAT,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
            $sheet->getHighestRow() => ['font' => ['bold' => true]],
        ];
    }
}
