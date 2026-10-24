<?php

namespace App\Filament\Resources\WifiNetworks\Pages;

use App\Filament\Resources\WifiNetworks\Concerns\ShowsWifiPasswords;
use App\Filament\Resources\WifiNetworks\WifiNetworkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWifiNetworks extends ListRecords
{
    use ShowsWifiPasswords;

    protected static string $resource = WifiNetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
