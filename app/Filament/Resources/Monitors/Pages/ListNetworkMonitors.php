<?php

namespace App\Filament\Resources\Monitors\Pages;

use App\Filament\Resources\Monitors\NetworkMonitorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNetworkMonitors extends ListRecords
{
    protected static string $resource = NetworkMonitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
