<?php

namespace App\Filament\Resources\HostedServices\Pages;

use App\Filament\Resources\HostedServices\HostedServiceActions;
use App\Filament\Resources\HostedServices\HostedServiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHostedService extends ViewRecord
{
    protected static string $resource = HostedServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Muat ulang halaman agar riwayat pembayaran & tanggal berlaku ikut diperbarui.
            HostedServiceActions::recordPayment()
                ->successRedirectUrl(fn (): string => $this->getResource()::getUrl('view', ['record' => $this->getRecord()])),
            EditAction::make(),
        ];
    }
}
