<?php

namespace App\Filament\Resources\UkomReports\Pages;

use App\Filament\Resources\UkomReports\UkomReportActions;
use App\Filament\Resources\UkomReports\UkomReportResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewUkomReport extends ViewRecord
{
    protected static string $resource = UkomReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            UkomReportActions::downloadDocx(),
            UkomReportActions::downloadPdf(),
            UkomReportActions::finalize(),
            UkomReportActions::uploadSignedScan(),
            EditAction::make()->label('Ubah draf'),
            ActionGroup::make([
                UkomReportActions::downloadScan(),
                UkomReportActions::duplicate(),
                DeleteAction::make()
                    ->label('Arsipkan')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->modalHeading('Arsipkan laporan?')
                    ->modalDescription('Laporan disembunyikan dari daftar (filter "Arsip" untuk melihatnya). Nomor surat tetap terpakai.')
                    ->modalSubmitActionLabel('Arsipkan')
                    ->successNotificationTitle('Laporan diarsipkan'),
                RestoreAction::make()
                    ->label('Pulihkan dari arsip')
                    ->successNotificationTitle('Laporan dipulihkan'),
            ]),
        ];
    }
}
