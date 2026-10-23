<?php

namespace App\Filament\Resources\Monitors\Pages;

use App\Filament\Resources\Monitors\ApplicationMonitorResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditApplicationMonitor extends EditRecord
{
    protected static string $resource = ApplicationMonitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
