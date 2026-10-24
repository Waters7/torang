<?php

namespace App\Filament\Resources\HostedServices\Pages;

use App\Filament\Resources\HostedServices\HostedServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditHostedService extends EditRecord
{
    protected static string $resource = HostedServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
