<?php

namespace App\Filament\Resources\WifiNetworks\Pages;

use App\Actions\Wifi\SaveWifiNetwork;
use App\Filament\Resources\WifiNetworks\WifiNetworkResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWifiNetwork extends CreateRecord
{
    protected static string $resource = WifiNetworkResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveWifiNetwork::class)->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
