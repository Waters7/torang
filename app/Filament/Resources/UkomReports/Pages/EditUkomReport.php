<?php

namespace App\Filament\Resources\UkomReports\Pages;

use App\Actions\Ukom\SaveUkomReport;
use App\Filament\Concerns\PrefixesFormValidationErrors;
use App\Filament\Resources\UkomReports\UkomReportResource;
use App\Filament\Resources\UkomReports\UkomReportWizard;
use App\Models\UkomReport;
use App\Models\UkomReportItem;
use App\Services\Ukom\UkomLetterNumber;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ubah draf lewat wizard yang sama; langkah boleh dilompati.
 */
class EditUkomReport extends EditRecord
{
    use HasWizard;
    use PrefixesFormValidationErrors;

    protected static string $resource = UkomReportResource::class;

    protected static ?string $title = 'Ubah draf laporan UKOM';

    public function getSteps(): array
    {
        return UkomReportWizard::steps();
    }

    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Simpan draf');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var UkomReport $report */
        $report = $this->getRecord();

        $data['auto_number'] = $report->letter_sequence !== null
            && $report->letter_number === app(UkomLetterNumber::class)->format($report->letter_sequence, $report->letter_year, $report->letter_date->month);
        $data['items'] = $report->items->mapWithKeys(fn (UkomReportItem $item): array => [
            (string) Str::uuid() => $item->only(['description', 'qty_1', 'unit_1', 'qty_2', 'unit_2', 'note', 'unit_price', 'display_text']),
        ])->all();

        return $data;
    }

    /**
     * @param  UkomReport  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SaveUkomReport::class)->update($record, $data);
        } catch (ValidationException $exception) {
            throw $this->prefixValidationErrors($exception);
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Draf laporan tersimpan';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
