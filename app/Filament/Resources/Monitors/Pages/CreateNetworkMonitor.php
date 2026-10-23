<?php

namespace App\Filament\Resources\Monitors\Pages;

use App\Filament\Resources\Monitors\NetworkMonitorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNetworkMonitor extends CreateRecord
{
    protected static string $resource = NetworkMonitorResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return MonitorPageData::forCreate(NetworkMonitorResource::group(), $data);
    }
}
