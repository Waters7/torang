<?php

namespace App\Filament\Resources\HostedServices\Widgets;

use App\Enums\HostedServiceState;
use App\Services\Hosting\HostingBudget;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HostingOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $budget = app(HostingBudget::class);
        $counts = $budget->stateCounts();
        $count = fn (HostedServiceState $state): int => $counts[$state->value] ?? 0;

        return [
            Stat::make('Aktif', $count(HostedServiceState::Active))
                ->description('Berlaku lebih dari '.HostedServiceState::EXPIRING_DAYS.' hari')
                ->color('success'),
            Stat::make('Segera berakhir', $count(HostedServiceState::Expiring))
                ->description('≤ '.HostedServiceState::EXPIRING_DAYS.' hari lagi')
                ->color($count(HostedServiceState::Expiring) > 0 ? 'warning' : 'gray'),
            Stat::make('Kedaluwarsa', $count(HostedServiceState::Expired))
                ->description('Belum diperpanjang/dihentikan')
                ->color($count(HostedServiceState::Expired) > 0 ? 'danger' : 'gray'),
            Stat::make('Estimasi biaya per tahun', Format::rupiah($budget->totalAnnualCost()))
                ->description('Layanan aktif, disetahunkan dari siklus tagihan'),
        ];
    }
}
