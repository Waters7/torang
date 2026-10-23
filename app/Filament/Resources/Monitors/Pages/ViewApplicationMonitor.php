<?php

namespace App\Filament\Resources\Monitors\Pages;

use App\Filament\Resources\Monitors\ApplicationMonitorResource;
use App\Filament\Resources\Monitors\MonitorResource;
use App\Filament\Resources\Monitors\Widgets\MonitorLatencyChart;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewApplicationMonitor extends ViewRecord
{
    protected static string $resource = ApplicationMonitorResource::class;

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
