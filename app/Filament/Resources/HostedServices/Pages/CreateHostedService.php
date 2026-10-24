<?php

namespace App\Filament\Resources\HostedServices\Pages;

use App\Filament\Resources\HostedServices\HostedServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHostedService extends CreateRecord
{
    protected static string $resource = HostedServiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
