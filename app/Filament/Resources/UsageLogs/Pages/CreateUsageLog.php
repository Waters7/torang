<?php

namespace App\Filament\Resources\UsageLogs\Pages;

use App\Actions\Bookings\RecordUsageLog;
use App\Filament\Concerns\PrefixesFormValidationErrors;
use App\Filament\Resources\UsageLogs\UsageLogResource;
use App\Models\Booking;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CreateUsageLog extends CreateRecord
{
    use PrefixesFormValidationErrors;

    protected static string $resource = UsageLogResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $booking = Booking::findOrFail($data['booking_id']);

        try {
            return app(RecordUsageLog::class)->handle($booking, Arr::except($data, 'booking_id'), auth()->user());
        } catch (ValidationException $exception) {
            throw $this->prefixValidationErrors($exception);
        }
    }
}
