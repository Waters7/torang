<?php

namespace App\Filament\Resources\WifiNetworks\Pages;

use App\Filament\Resources\WifiNetworks\Concerns\ShowsWifiPasswords;
use App\Filament\Resources\WifiNetworks\WifiNetworkResource;
use App\Filament\Resources\WifiNetworks\WifiPasswordActions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWifiNetwork extends ViewRecord
{
    use ShowsWifiPasswords;

    protected static string $resource = WifiNetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            WifiPasswordActions::reveal(),
            WifiPasswordActions::qrCard(),
            EditAction::make(),
        ];
    }
}
