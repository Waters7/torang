<?php

namespace App\Filament\Resources\UkomReports\Pages;

use App\Filament\Resources\UkomReports\UkomReportActions;
use App\Filament\Resources\UkomReports\UkomReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUkomReports extends ListRecords
{
    protected static string $resource = UkomReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            UkomReportActions::exportExcel(),
            CreateAction::make()->label('Buat laporan'),
        ];
    }
}
