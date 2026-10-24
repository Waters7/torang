<?php

namespace App\Exports\Sheets;

use App\Services\Hosting\HostingBudget;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProviderBudgetSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Anggaran per provider';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Provider', 'Layanan aktif', 'Estimasi biaya per tahun'];
    }

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        $rows = app(HostingBudget::class)->perProvider();

        return [
            ...$rows->map(fn (array $row): array => [$row['provider'], $row['services'], $row['annual_cost']])->all(),
            ['Total', $rows->sum('services'), round($rows->sum('annual_cost'), 2)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return ['C' => HostedServicesSheet::MONEY_FORMAT];
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
