<?php

namespace App\Filament\Resources\ExamOrganizers\Pages;

use App\Filament\Resources\ExamOrganizers\ExamOrganizerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExamOrganizers extends ManageRecords
{
    protected static string $resource = ExamOrganizerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
