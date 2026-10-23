<?php

namespace App\Filament\Resources\Monitors\Pages;

use App\Filament\Resources\Monitors\ApplicationMonitorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateApplicationMonitor extends CreateRecord
{
    protected static string $resource = ApplicationMonitorResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return MonitorPageData::forCreate(ApplicationMonitorResource::group(), $data);
    }
}
