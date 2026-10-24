<?php

namespace App\Services\Hosting;

use App\Services\SettingsService;
use App\Support\Format;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Rekap anggaran hosting, VPS & domain dalam PDF A4 lanskap: estimasi per provider dan daftar layanan aktif.
 */
class HostingBudgetPdf
{
    public function __construct(
        private readonly HostingBudget $budget,
        private readonly SettingsService $settings,
    ) {}

    public function output(): string
    {
        return Pdf::loadView('pdf.hosting-budget', [
            'providers' => $this->budget->perProvider(),
            'services' => $this->budget->services(),
            'total' => $this->budget->totalAnnualCost(),
            'institution' => $this->settings->get('identity.institution_name'),
            'unit' => $this->settings->get('identity.unit_name'),
            'generatedAt' => Format::date(now()),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function filename(): string
    {
        return 'rekap-hosting-domain-'.now()->format('Y-m-d').'.pdf';
    }
}
