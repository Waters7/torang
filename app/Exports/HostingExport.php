<?php

namespace App\Exports;

use App\Exports\Sheets\HostedServicesSheet;
use App\Exports\Sheets\ProviderBudgetSheet;
use App\Exports\Sheets\ServicePaymentsSheet;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Ekspor penganggaran hosting & domain: daftar layanan, estimasi per provider, pembayaran setahun.
 */
class HostingExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly int $year) {}

    /**
     * @return list<object>
     */
    public function sheets(): array
    {
        return [
            new HostedServicesSheet,
            new ProviderBudgetSheet,
            new ServicePaymentsSheet($this->year),
        ];
    }
}
