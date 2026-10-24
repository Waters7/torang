<?php

namespace App\Filament\Resources\HostedServices\Pages;

use App\Filament\Resources\HostedServices\HostedServiceActions;
use App\Filament\Resources\HostedServices\HostedServiceResource;
use App\Filament\Resources\HostedServices\Widgets\DueServicesTable;
use App\Filament\Resources\HostedServices\Widgets\HostingOverview;
use App\Filament\Resources\HostedServices\Widgets\ProviderCostTable;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListHostedServices extends ListRecords
{
    protected static string $resource = HostedServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                HostedServiceActions::exportExcel(),
                HostedServiceActions::exportPdf(),
            ])
                ->label('Ekspor')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->button(),
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            HostingOverview::class,
            DueServicesTable::class,
            ProviderCostTable::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
