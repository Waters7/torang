<?php

namespace App\Filament\Resources\WifiNetworks\Pages;

use App\Actions\Wifi\SaveWifiNetwork;
use App\Filament\Resources\WifiNetworks\WifiNetworkResource;
use App\Models\WifiNetwork;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWifiNetwork extends EditRecord
{
    protected static string $resource = WifiNetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Password tidak pernah dimuat ke form (dan ke state Livewire/HTML).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['password']);

        return $data;
    }

    /**
     * @param  WifiNetwork  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(SaveWifiNetwork::class)->update($record, $data, auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
