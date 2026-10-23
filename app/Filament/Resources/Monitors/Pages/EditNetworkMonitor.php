<?php

namespace App\Filament\Resources\Monitors\Pages;

use App\Filament\Resources\Monitors\NetworkMonitorResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditNetworkMonitor extends EditRecord
{
    protected static string $resource = NetworkMonitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
