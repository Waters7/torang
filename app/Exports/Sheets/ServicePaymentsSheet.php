<?php

namespace App\Exports\Sheets;

use App\Models\ServicePayment;
use App\Services\Hosting\HostingBudget;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ServicePaymentsSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly int $year) {}

    public function title(): string
    {
        return "Pembayaran {$this->year}";
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Tanggal bayar', 'Layanan', 'Identitas', 'Provider', 'Periode mulai', 'Periode sampai', 'Nominal', 'Dicatat oleh', 'Catatan'];
    }

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        $payments = app(HostingBudget::class)->payments($this->year);

        return [
            ...$payments->map(fn (ServicePayment $payment): array => [
                Date::dateTimeToExcel($payment->paid_at),
                $payment->service->name,
                $payment->service->identifier,
                $payment->service->provider->name,
                Date::dateTimeToExcel($payment->period_start),
                Date::dateTimeToExcel($payment->period_end),
                (float) $payment->amount,
                $payment->recordedBy?->name,
                $payment->notes,
            ])->all(),
            ['Total', null, null, null, null, null, round((float) $payments->sum('amount'), 2), null, null],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'A' => HostedServicesSheet::DATE_FORMAT,
            'E' => HostedServicesSheet::DATE_FORMAT,
            'F' => HostedServicesSheet::DATE_FORMAT,
            'G' => HostedServicesSheet::MONEY_FORMAT,
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
