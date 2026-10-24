<?php

namespace App\Exports\Sheets;

use App\Models\HostedService;
use App\Services\Hosting\HostingBudget;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HostedServicesSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    public const DATE_FORMAT = 'dd/mm/yyyy';

    public const MONEY_FORMAT = '"Rp"#,##0';

    public function title(): string
    {
        return 'Layanan';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Layanan', 'Identitas', 'Provider', 'Jenis', 'Dipakai oleh', 'PIC', 'Pembayaran terakhir', 'Berlaku sampai',
            'Status', 'Siklus', 'Biaya per siklus', 'Estimasi per tahun', 'Perpanjangan otomatis',
        ];
    }

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        return app(HostingBudget::class)->services(includeStopped: true)
            ->map(fn (HostedService $service): array => [
                $service->name,
                $service->identifier,
                $service->provider->name,
                $service->type->label(),
                $service->used_by,
                $service->pic?->name,
                $service->last_paid_at ? Date::dateTimeToExcel($service->last_paid_at) : null,
                Date::dateTimeToExcel($service->expires_at),
                $service->state()->label(),
                $service->billing_cycle->label(),
                (float) $service->cost,
                $service->annualCost(),
                $service->auto_renew ? 'Ya' : 'Tidak',
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'G' => self::DATE_FORMAT,
            'H' => self::DATE_FORMAT,
            'K' => self::MONEY_FORMAT,
            'L' => self::MONEY_FORMAT,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
