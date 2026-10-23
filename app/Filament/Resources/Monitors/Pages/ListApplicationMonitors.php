<?php

namespace App\Filament\Resources\Monitors\Pages;

use App\Filament\Resources\Monitors\ApplicationMonitorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApplicationMonitors extends ListRecords
{
    protected static string $resource = ApplicationMonitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
