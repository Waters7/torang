<?php

namespace App\Filament\Resources\UsageLogs\Pages;

use App\Actions\Bookings\RecordUsageLog;
use App\Filament\Concerns\PrefixesFormValidationErrors;
use App\Filament\Resources\UsageLogs\UsageLogResource;
use App\Models\UsageLog;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class EditUsageLog extends EditRecord
{
    use PrefixesFormValidationErrors;

    protected static string $resource = UsageLogResource::class;

    /**
     * @param  UsageLog  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(RecordUsageLog::class)->handle($record->booking, Arr::except($data, 'booking_id'), auth()->user());
        } catch (ValidationException $exception) {
            throw $this->prefixValidationErrors($exception);
        }
    }
}
