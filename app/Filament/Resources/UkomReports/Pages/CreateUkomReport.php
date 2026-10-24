<?php

namespace App\Filament\Resources\UkomReports\Pages;

use App\Actions\Ukom\SaveUkomReport;
use App\Filament\Concerns\PrefixesFormValidationErrors;
use App\Filament\Resources\UkomReports\UkomReportResource;
use App\Filament\Resources\UkomReports\UkomReportWizard;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateUkomReport extends CreateRecord
{
    use HasWizard;
    use PrefixesFormValidationErrors;

    protected static string $resource = UkomReportResource::class;

    protected static ?string $title = 'Buat laporan UKOM';

    public function getSteps(): array
    {
        return UkomReportWizard::steps();
    }

    protected function getSubmitFormAction(): Action
    {
        return parent::getSubmitFormAction()->label('Simpan draf');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SaveUkomReport::class)->create($data, auth()->user());
        } catch (ValidationException $exception) {
            throw $this->prefixValidationErrors($exception);
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Draf laporan tersimpan';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
