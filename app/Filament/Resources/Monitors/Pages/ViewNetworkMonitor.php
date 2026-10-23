<?php

namespace App\Filament\Resources\Monitors\Pages;

use App\Filament\Resources\Monitors\MonitorResource;
use App\Filament\Resources\Monitors\NetworkMonitorResource;
use App\Filament\Resources\Monitors\Widgets\MonitorLatencyChart;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewNetworkMonitor extends ViewRecord
{
    protected static string $resource = NetworkMonitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MonitorResource::checkNowAction(),
            EditAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            MonitorLatencyChart::class,
        ];
    }
}
